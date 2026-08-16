<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();
require_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$newsletterId = (int) ($input['newsletter_id'] ?? 0);
$meta = $input['meta'] ?? [];
$sections = $input['sections'] ?? [];
if (!$newsletterId || !is_array($sections)) {
    json_response(['ok' => false, 'message' => 'Invalid newsletter data.'], 422);
}

$slug = slugify((string) ($meta['slug'] ?? ($meta['title'] ?? 'newsletter')));
$status = in_array(($meta['status'] ?? 'draft'), ['draft','ready','scheduled','sending','sent','archived'], true) ? $meta['status'] : 'draft';
$direction = in_array(($meta['direction'] ?? 'rtl'), ['auto','ltr','rtl'], true) ? $meta['direction'] : 'rtl';

db()->beginTransaction();
try {
    db()->prepare(
        'update newsletters set internal_name=?, title=?, subject=?, preview_text=?, slug=?, issue_number=?, sender_name=?, sender_email=?, reply_to=?, direction=?, status=?, updated_at=now() where id=?'
    )->execute([
        trim((string) ($meta['internal_name'] ?? '')),
        trim((string) ($meta['title'] ?? 'Newsletter')),
        trim((string) ($meta['subject'] ?? 'Newsletter')),
        trim((string) ($meta['preview_text'] ?? '')),
        $slug,
        trim((string) ($meta['issue_number'] ?? '')),
        trim((string) ($meta['sender_name'] ?? setting('sender_name', 'Newsletter'))),
        trim((string) ($meta['sender_email'] ?? setting('sender_email', ''))),
        trim((string) ($meta['reply_to'] ?? setting('reply_to', ''))),
        $direction,
        $status,
        $newsletterId,
    ]);

    db()->prepare('delete from newsletter_sections where newsletter_id = ?')->execute([$newsletterId]);
    foreach ($sections as $sectionIndex => $section) {
        db()->prepare('insert into newsletter_sections (newsletter_id,section_type,title,sort_order,settings_json,created_at,updated_at) values (?,?,?,?,?,now(),now())')
            ->execute([
                $newsletterId,
                $section['section_type'] ?? 'content',
                trim((string) ($section['title'] ?? '')),
                $sectionIndex,
                json_encode($section['settings'] ?? [], JSON_UNESCAPED_SLASHES),
            ]);
        $sectionId = (int) db()->lastInsertId();
        foreach (($section['blocks'] ?? []) as $blockIndex => $block) {
            $content = $block['content'] ?? [];
            if (($block['block_type'] ?? '') === 'text') {
                $content['html'] = clean_html((string) ($content['html'] ?? ''));
            }
            db()->prepare('insert into newsletter_blocks (newsletter_id,section_id,block_type,sort_order,content_json,settings_json,created_at,updated_at) values (?,?,?,?,?,?,now(),now())')
                ->execute([
                    $newsletterId,
                    $sectionId,
                    $block['block_type'] ?? 'text',
                    $blockIndex,
                    json_encode($content, JSON_UNESCAPED_SLASHES),
                    json_encode($block['settings'] ?? [], JSON_UNESCAPED_SLASHES),
                ]);
        }
    }

    db()->commit();
    json_response(['ok' => true, 'slug' => $slug]);
} catch (Throwable $e) {
    db()->rollBack();
    json_response(['ok' => false, 'message' => 'The newsletter could not be saved.'], 500);
}
