<?php

declare(strict_types=1);

function newsletter_payload(int $newsletterId): array
{
    $newsletter = db_row('select * from newsletters where id = ?', [$newsletterId]);
    if (!$newsletter) {
        return [];
    }

    $sections = db_all('select * from newsletter_sections where newsletter_id = ? order by sort_order, id', [$newsletterId]);
    foreach ($sections as &$section) {
        $section['settings'] = json_decode($section['settings_json'] ?: '{}', true) ?: [];
        $blocks = db_all('select * from newsletter_blocks where section_id = ? order by sort_order, id', [$section['id']]);
        foreach ($blocks as &$block) {
            $block['content'] = json_decode($block['content_json'] ?: '{}', true) ?: [];
            $block['settings'] = json_decode($block['settings_json'] ?: '{}', true) ?: [];
            if (!empty($block['content']['image_id'])) {
                $block['media'] = db_row('select * from media where id = ?', [(int) $block['content']['image_id']]);
            }
        }
        $section['blocks'] = $blocks;
    }

    return ['newsletter' => $newsletter, 'sections' => $sections];
}

function render_newsletter_html(array $payload, string $mode = 'web', ?array $subscriber = null): string
{
    $newsletter = $payload['newsletter'] ?? [];
    $sections = $payload['sections'] ?? [];
    $direction = in_array(($newsletter['direction'] ?? 'auto'), ['auto', 'ltr', 'rtl'], true) ? $newsletter['direction'] : 'auto';
    $theme = setting('theme', [
        'primary' => '#2563eb',
        'secondary' => '#0f172a',
        'background' => '#f7f8fb',
        'text' => '#1f2937',
        'link' => '#2563eb',
        'button' => '#2563eb',
        'radius' => '8',
        'email_width' => '680',
    ]);

    ob_start();
    ?>
<div class="nl-root" dir="<?= h($direction) ?>" style="background:<?= h($theme['background'] ?? '#f7f8fb') ?>;color:<?= h($theme['text'] ?? '#1f2937') ?>;font-family:Arial,Helvetica,sans-serif;">
    <div class="nl-container" style="max-width:<?= (int) ($theme['email_width'] ?? 680) ?>px;margin:0 auto;background:#fff;">
        <?php foreach ($sections as $section): ?>
            <?php if (empty($section['title']) && empty($section['blocks'])) continue; ?>
            <section style="padding:26px 32px;border-bottom:1px solid #eef2f7;">
                <?php if (!empty($section['title'])): ?>
                    <h2 style="font-size:18px;margin:0 0 18px;color:<?= h($theme['secondary'] ?? '#0f172a') ?>;"><?= h($section['title']) ?></h2>
                <?php endif; ?>
                <?php foreach ($section['blocks'] as $block): ?>
                    <?= render_block_html($block, $theme, $mode) ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <footer style="padding:24px 32px;color:#64748b;font-size:13px;">
            <?= render_newsletter_footer_html($theme, $newsletter, $subscriber) ?>
        </footer>
    </div>
</div>
    <?php
    return (string) ob_get_clean();
}

function render_block_html(array $block, array $theme, string $mode): string
{
    $content = $block['content'] ?? [];
    $buttonColor = h($theme['button'] ?? '#2563eb');
    $linkColor = h($theme['link'] ?? '#2563eb');
    $image = $block['media']['file_path'] ?? ($content['image_url'] ?? '');
    $imageUrl = $image ? upload_url_from_path((string) $image) : '';
    ob_start();

    if ($block['block_type'] === 'headline'): ?>
        <h3 style="font-size:<?= (int) ($content['size'] ?? 28) ?>px;text-align:<?= h(newsletter_align($content['align'] ?? 'start')) ?>;margin:0 0 14px;">
            <?php if (!empty($content['url'])): ?><a style="color:<?= $linkColor ?>;text-decoration:none;" href="<?= h($content['url']) ?>"><?php endif; ?>
            <?= h($content['text'] ?? 'Headline') ?>
            <?php if (!empty($content['url'])): ?></a><?php endif; ?>
        </h3>
    <?php elseif ($block['block_type'] === 'text'): ?>
        <div style="font-size:16px;line-height:1.6;margin-bottom:16px;text-align:<?= h(newsletter_align($content['align'] ?? 'start')) ?>;"><?= clean_html((string) ($content['html'] ?? '<p>Write your text here.</p>')) ?></div>
    <?php elseif ($block['block_type'] === 'image'): ?>
        <?php if ($imageUrl): ?><img src="<?= h($imageUrl) ?>" alt="<?= h($content['alt'] ?? '') ?>" style="display:block;width:100%;height:auto;border-radius:<?= (int) ($theme['radius'] ?? 8) ?>px;margin-bottom:18px;"><?php endif; ?>
    <?php elseif ($block['block_type'] === 'button'): ?>
        <p style="margin:18px 0;"><a href="<?= h($content['url'] ?? '#') ?>" style="display:inline-block;background:<?= $buttonColor ?>;color:#fff;text-decoration:none;padding:11px 18px;border-radius:6px;font-weight:bold;"><?= h($content['text'] ?? 'Read More') ?></a></p>
    <?php elseif ($block['block_type'] === 'divider'): ?>
        <hr style="border:0;border-top:1px solid #e5e7eb;margin:22px 0;">
    <?php else: ?>
        <article style="margin-bottom:24px;">
            <?php if ($imageUrl): ?><a href="<?= h($content['url'] ?? '#') ?>"><img src="<?= h($imageUrl) ?>" alt="<?= h($content['image_alt'] ?? '') ?>" style="display:block;width:100%;height:auto;border-radius:<?= (int) ($theme['radius'] ?? 8) ?>px;margin-bottom:14px;"></a><?php endif; ?>
            <?php if (!empty($content['category'])): ?><div style="font-size:12px;font-weight:bold;letter-spacing:.04em;text-transform:uppercase;color:<?= $linkColor ?>;"><?= h($content['category']) ?></div><?php endif; ?>
            <h3 style="font-size:23px;line-height:1.2;margin:7px 0 8px;color:#111827;">
                <a style="color:inherit;text-decoration:none;" href="<?= h($content['url'] ?? '#') ?>"><?= h($content['headline'] ?? 'Article headline') ?></a>
            </h3>
            <p style="font-size:15px;line-height:1.55;margin:0 0 12px;color:#475569;"><?= h($content['description'] ?? 'Short article description.') ?></p>
            <?php if (!empty($content['url'])): ?><a style="color:<?= $linkColor ?>;font-weight:bold;" href="<?= h($content['url']) ?>"><?= h($content['button_text'] ?? 'Read More') ?></a><?php endif; ?>
        </article>
    <?php endif;

    return (string) ob_get_clean();
}

function newsletter_align(string $align): string
{
    if ($align === 'center') {
        return 'center';
    }
    if ($align === 'end') {
        return 'end';
    }
    return 'start';
}

function render_newsletter_footer_html(array $theme, array $newsletter = [], ?array $subscriber = null): string
{
    $linkColor = h($theme['link'] ?? '#2563eb');
    $iconStyle = 'display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;margin:0 4px;border:1px solid #dbe3ef;border-radius:999px;color:' . $linkColor . ';text-decoration:none;';
    $links = [
        ['Instagram', 'https://www.instagram.com/omqpro', social_icon_svg('instagram')],
        ['Facebook', 'https://www.facebook.com/omqpro', social_icon_svg('facebook')],
        ['X', 'https://x.com/omqpro', social_icon_svg('x')],
        ['LinkedIn', 'https://www.linkedin.com/company/omqpro', social_icon_svg('linkedin')],
    ];

    ob_start();
    ?>
    <div style="text-align:center;">
        <div style="margin:0 0 12px;font-weight:bold;color:#0f172a;">@omqpro</div>
        <div style="margin:0 0 14px;">
            <?php foreach ($links as [$label, $url, $icon]): ?>
                <a href="<?= h($url) ?>" aria-label="<?= h($label) ?>" title="<?= h($label) ?>" style="<?= h($iconStyle) ?>"><?= $icon ?></a>
            <?php endforeach; ?>
        </div>
        <p style="margin:0;color:#64748b;">
            <a style="color:<?= $linkColor ?>" href="<?= h(app_url('subscribe')) ?>">Subscribe</a>
            <?php if ($subscriber): ?>
                - <a style="color:<?= $linkColor ?>" href="<?= h(app_url('unsubscribe?token=' . $subscriber['unsubscribe_token'])) ?>">Unsubscribe</a>
            <?php endif; ?>
            - <a style="color:<?= $linkColor ?>" href="<?= h(app_url($newsletter['slug'] ?? '')) ?>">Web version</a>
        </p>
    </div>
    <?php
    return (string) ob_get_clean();
}

function social_icon_svg(string $name): string
{
    $attrs = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    if ($name === 'instagram') {
        return '<svg ' . $attrs . '><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1"></circle></svg>';
    }
    if ($name === 'facebook') {
        return '<svg ' . $attrs . '><path d="M15 8h-2a2 2 0 0 0-2 2v3H8v4h3v4h4v-4h3l1-4h-4v-2a1 1 0 0 1 1-1h3V6h-4z"></path></svg>';
    }
    if ($name === 'linkedin') {
        return '<svg ' . $attrs . '><path d="M16 8a6 6 0 0 1 6 6v6h-4v-6a2 2 0 0 0-4 0v6h-4V9h4v2"></path><rect x="2" y="9" width="4" height="11"></rect><circle cx="4" cy="4" r="2"></circle></svg>';
    }
    return '<svg ' . $attrs . '><path d="M4 4l16 16"></path><path d="M20 4L4 20"></path></svg>';
}
