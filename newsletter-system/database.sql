create table admins (
    id int unsigned auto_increment primary key,
    name varchar(160) not null,
    email varchar(190) not null unique,
    password_hash varchar(255) not null,
    created_at datetime not null,
    updated_at datetime not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table subscribers (
    id int unsigned auto_increment primary key,
    email varchar(190) not null unique,
    first_name varchar(120) null,
    last_name varchar(120) null,
    status varchar(32) not null default 'active',
    subscription_token varchar(128) not null,
    unsubscribe_token varchar(128) not null,
    source varchar(80) null,
    subscribed_at datetime null,
    unsubscribed_at datetime null,
    created_at datetime not null,
    updated_at datetime not null,
    index(status),
    unique key unsubscribe_token_unique (unsubscribe_token)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table newsletters (
    id int unsigned auto_increment primary key,
    internal_name varchar(190) not null,
    title varchar(190) not null,
    subject varchar(190) not null,
    preview_text text null,
    slug varchar(190) not null unique,
    issue_number varchar(80) null,
    sender_name varchar(160) null,
    sender_email varchar(190) null,
    reply_to varchar(190) null,
    direction varchar(8) not null default 'rtl',
    status varchar(32) not null default 'draft',
    published_at datetime null,
    sent_at datetime null,
    created_at datetime not null,
    updated_at datetime not null,
    index(status)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table newsletter_sections (
    id int unsigned auto_increment primary key,
    newsletter_id int unsigned not null,
    section_type varchar(60) not null default 'content',
    title varchar(190) null,
    sort_order int not null default 0,
    settings_json json null,
    created_at datetime not null,
    updated_at datetime not null,
    foreign key (newsletter_id) references newsletters(id) on delete cascade,
    index(newsletter_id, sort_order)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table newsletter_blocks (
    id int unsigned auto_increment primary key,
    newsletter_id int unsigned not null,
    section_id int unsigned not null,
    block_type varchar(60) not null,
    sort_order int not null default 0,
    content_json json null,
    settings_json json null,
    created_at datetime not null,
    updated_at datetime not null,
    foreign key (newsletter_id) references newsletters(id) on delete cascade,
    foreign key (section_id) references newsletter_sections(id) on delete cascade,
    index(section_id, sort_order)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table media (
    id int unsigned auto_increment primary key,
    file_name varchar(190) not null,
    file_path varchar(255) not null,
    original_file_path varchar(255) null,
    mime_type varchar(80) not null,
    width int unsigned not null default 0,
    height int unsigned not null default 0,
    file_size int unsigned not null default 0,
    alt_text varchar(255) null,
    created_at datetime not null,
    updated_at datetime not null,
    index(file_name)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table newsletter_links (
    id int unsigned auto_increment primary key,
    newsletter_id int unsigned not null,
    block_id int unsigned null,
    url text not null,
    tracking_token varchar(128) not null unique,
    created_at datetime not null,
    foreign key (newsletter_id) references newsletters(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table email_queue (
    id int unsigned auto_increment primary key,
    newsletter_id int unsigned not null,
    subscriber_id int unsigned not null,
    recipient_email varchar(190) not null,
    status varchar(32) not null default 'queued',
    tracking_token varchar(128) not null,
    attempts int unsigned not null default 0,
    last_error text null,
    scheduled_at datetime not null,
    sent_at datetime null,
    created_at datetime not null,
    updated_at datetime not null,
    foreign key (newsletter_id) references newsletters(id) on delete cascade,
    foreign key (subscriber_id) references subscribers(id) on delete cascade,
    unique key one_queue_item_per_subscriber (newsletter_id, subscriber_id),
    index(status, scheduled_at),
    unique key tracking_token_unique (tracking_token)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table email_events (
    id int unsigned auto_increment primary key,
    newsletter_id int unsigned null,
    subscriber_id int unsigned null,
    event_type varchar(32) not null,
    link_id int unsigned null,
    ip_hash varchar(128) null,
    user_agent varchar(255) null,
    created_at datetime not null,
    index(newsletter_id, event_type),
    index(subscriber_id, event_type)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table settings (
    id int unsigned auto_increment primary key,
    setting_key varchar(120) not null unique,
    setting_value longtext null,
    updated_at datetime not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

insert into settings (setting_key, setting_value, updated_at) values
('sender_name', 'Newsletter', now()),
('sender_email', '', now()),
('reply_to', '', now()),
('footer_html', '<p style="margin:0 0 10px;font-weight:bold;color:#0f172a;">Follow @omqpro</p><p style="margin:0;"><a href="https://www.instagram.com/omqpro">Instagram</a> | <a href="https://www.facebook.com/omqpro">Facebook</a> | <a href="https://x.com/omqpro">X</a> | <a href="https://www.linkedin.com/company/omqpro">LinkedIn</a></p>', now()),
('theme', '{"primary":"#2563eb","secondary":"#0f172a","background":"#f7f8fb","text":"#1f2937","link":"#2563eb","button":"#2563eb","radius":8,"email_width":680}', now()),
('smtp', '{"host":"","port":587,"username":"","password":"","encryption":"tls","batch_size":50,"batch_delay_seconds":60}', now());
