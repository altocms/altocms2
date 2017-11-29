<?php

$config['params'] =
    [
        [
            'label' => 'action.admin.set_section_general',
            'type' => 'section',
        ],
        [
            'label' => 'action.admin.set_view_name',
            'type' => 'text',
            'config' => 'view.name',
        ],
        [
            'label' => 'action.admin.set_view_description',
            'type' => 'text',
            'config' => 'view.description',
        ],
        [
            'label' => 'action.admin.set_view_keywords',
            'type' => 'text',
            'config' => 'view.keywords',
        ],

        [
            'label' => 'action.admin.set_general_close',
            'type' => 'checkbox',
            'config' => 'general.close.mode',
        ],
        [
            'label' => 'action.admin.set_general_reg_invite',
            'type' => 'checkbox',
            'config' => 'general.reg.invite',
        ],
        [
            'label' => 'action.admin.set_general_reg_activation',
            'type' => 'checkbox',
            'config' => 'general.reg.activation',
        ],
    ];

$config['sys'] =
    [
        [
            'label' => 'action.admin.set_section_sys_cookie',
            'type' => 'section',
        ],
        [
            'label' => 'action.admin.set_sys_cookie_host',
            'type' => 'text',
            'config' => 'sys.cookie.host',
            'valtype' => 'string',
            'default' => null,
        ],
        [
            'label' => 'action.admin.set_sys_cookie_path',
            'type' => 'text',
            'config' => 'sys.cookie.path',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_session_standart',
            'type' => 'checkbox',
            'config' => 'sys.session.standart',
        ],
        [
            'label' => 'action.admin.set_sys_session_name',
            'type' => 'text',
            'config' => 'sys.session.name',
            'valtype' => 'string',
        ],
        ['label' => 'action.admin.set_sys_session_timeout',
            'type' => 'text',
            'config' => 'sys.session.timeout',
            'valtype' => 'string',
            'default' => null,
        ],
        ['label' => 'action.admin.set_sys_session_host',
            'type' => 'text',
            'config' => 'sys.session.host',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_session_path',
            'type' => 'text',
            'config' => 'sys.session.path',
            'valtype' => 'string',
        ],

        [
            'label' => 'action.admin.set_section_sys_mail',
            'type' => 'section',
        ],
        [
            'label' => 'action.admin.set_sys_mail_from_email',
            'type' => 'text',
            'config' => 'sys.mail.from_email',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_mail_from_name',
            'type' => 'text',
            'config' => 'sys.mail.from_name',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_mail_charset',
            'type' => 'text',
            'config' => 'sys.mail.charset',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_mail_type',
            'type' => 'select',
            'options' => ['mail', 'sendmail', 'smtp'],
            'config' => 'sys.mail.type',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_mail_smtp_host',
            'type' => 'text',
            'config' => 'sys.mail.smtp.host',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_mail_smtp_port',
            'type' => 'text',
            'config' => 'sys.mail.smtp.port',
            'valtype' => 'integer',
        ],
        [
            'label' => 'action.admin.set_sys_mail_smtp_user',
            'type' => 'text',
            'config' => 'sys.mail.smtp.user',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_mail_smtp_password',
            'type' => 'password',
            'config' => 'sys.mail.smtp.password',
            'valtype' => 'password',
        ],
        [
            'label' => 'action.admin.set_sys_mail_smtp_auth',
            'type' => 'checkbox',
            'config' => 'sys.mail.smtp.auth',
        ],
        [
            'label' => 'action.admin.set_sys_mail_smtp_secure',
            'type' => 'select',
            'options' => ['' => '{{text_no}}', 'tls' => 'TLS', 'ssl' => 'SSL'],
            'config' => 'sys.mail.smtp.secure',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_mail_include_comment',
            'type' => 'checkbox',
            'config' => 'sys.mail.include_comment',
        ],
        [
            'label' => 'action.admin.set_sys_mail_include_talk',
            'type' => 'checkbox',
            'config' => 'sys.mail.include_talk',
        ],

        [
            'label' => 'action.admin.set_section_sys_logs',
            'type' => 'section',
        ],
        [
            'label' => 'action.admin.set_sys_logs_sql_query',
            'type' => 'checkbox',
            'config' => 'sys.logs.sql_query',
        ],
        [
            'label' => 'action.admin.set_sys_logs_sql_query_file',
            'type' => 'text',
            'config' => 'sys.logs.sql_query_file',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_logs_sql_error',
            'type' => 'checkbox',
            'config' => 'sys.logs.sql_error',
        ],
        ['label' => 'action.admin.set_sys_logs_sql_error_file',
            'type' => 'text',
            'config' => 'sys.logs.sql_error_file',
            'valtype' => 'string',
        ],
        [
            'label' => 'action.admin.set_sys_logs_cron_file',
            'type' => 'text',
            'config' => 'sys.logs.cron_file',
            'valtype' => 'string',
        ],
    ];

$config['acl'] =
    [
        [
            'label' => 'action.admin.set_section_acl',
            'type' => 'section',
        ],
        [
            'label' => 'action.admin.set_acl_create_blog_rating',
            'type' => 'text',
            'config' => 'acl.create.blog.rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_create_comment_rating',
            'type' => 'text',
            'config' => 'acl.create.comment.rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_create_comment_limit_time',
            'type' => 'text',
            'config' => 'acl.create.comment.limit_time',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_create_comment_limit_time_rating',
            'type' => 'text',
            'config' => 'acl.create.comment.limit_time_rating',
            'valtype' => 'string',
            'default' => '0',
        ], //
        [
            'label' => 'action.admin.set_acl_create_topic_limit_time',
            'type' => 'text',
            'config' => 'acl.create.topic.limit_time',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_create_topic_limit_time_rating',
            'type' => 'text',
            'config' => 'acl.create.topic.limit_time_rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_create_talk_limit_time',
            'type' => 'text',
            'config' => 'acl.create.talk.limit_time',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_create_talk_limit_time_rating',
            'type' => 'text',
            'config' => 'acl.create.talk.limit_time_rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_create_talk_comment_limit_time',
            'type' => 'text',
            'config' => 'acl.create.talk_comment.limit_time',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_create_talk_comment_limit_time_rating',
            'type' => 'text',
            'config' => 'acl.create.talk_comment.limit_time_rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_vote_comment_rating',
            'type' => 'text',
            'config' => 'acl.vote.comment.rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_vote_blog_rating',
            'type' => 'text',
            'config' => 'acl.vote.blog.rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_vote_topic_rating',
            'type' => 'text',
            'config' => 'acl.vote.topic.rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_vote_user_rating',
            'type' => 'text',
            'config' => 'acl.vote.user.rating',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_vote_topic_limit_time',
            'type' => 'text',
            'config' => 'acl.vote.topic.limit_time',
            'valtype' => 'string',
            'default' => '0',
        ],
        [
            'label' => 'action.admin.set_acl_vote_comment_limit_time',
            'type' => 'text',
            'config' => 'acl.vote.comment.limit_time',
            'valtype' => 'string',
            'default' => '0',
        ],
    ];

// ��������, ����� �� �������� ��������� gzip ��� ��������,
// ���� ����������� ����, �� ��� ��������� ����� �� ���������
$bApacheHasModHeaders = function_exists('apache_get_modules') && in_array('mod_headers', apache_get_modules());


$config['cssjs'] =
    [
        [
            'label' => 'action.admin.set_section_css',
            'type' => 'section',
        ],
        [
            'label' => 'action.admin.set_csscompress_merge',
            'help' => 'action.admin.set_csscompress_merge_notice',
            'type' => 'checkbox',
            'config' => 'compress.css.merge',
        ],
        [
            'label' => 'action.admin.set_csscompress_use',
            'help' => 'action.admin.set_csscompress_use_notice',
            'type' => 'checkbox',
            'config' => 'compress.css.use',
        ],
        $bApacheHasModHeaders
            ? [
                'label'  => 'action.admin.set_css_gzip',
                'help'   => 'action.admin.set_css_gzip_notice',
                'type'   => 'checkbox',
                'config' => 'compress.css.gzip',
            ]
            : [
                'type'   => 'alert',
                'label'  => 'action.admin.set_css_gzip_alert',
                'config' => 'compress.css.gzip',
                'value' => 0,
            ],
        [
            'label' => 'action.admin.set_csscompress_force',
            'help' => 'action.admin.set_csscompress_force_notice',
            'type' => 'checkbox',
            'config' => 'compress.css.force',
        ],
        [
            'label' => 'action.admin.set_section_js',
            'type' => 'section',
        ],
        [
            'label' => 'action.admin.set_jscompress_merge',
            'help' => 'action.admin.set_jscompress_merge_notice',
            'type' => 'checkbox',
            'config' => 'compress.js.merge',
        ],
        [
            'label' => 'action.admin.set_jscompress_use',
            'help' => 'action.admin.set_jscompress_use_notice',
            'type' => 'checkbox',
            'config' => 'compress.js.use',
        ],
        $bApacheHasModHeaders ?
            [
                'label'  => 'action.admin.set_js_gzip',
                'help'   => 'action.admin.set_js_gzip_notice',
                'type'   => 'checkbox',
                'config' => 'compress.js.gzip',
            ]
            : [
                'type'   => 'alert',
                'label'  => 'action.admin.set_js_gzip_alert',
                'config' => 'compress.css.gzip',
                'value' => 0,
            ],
        [
            'label' => 'action.admin.set_jscompress_force',
            'help' => 'action.admin.set_jscompress_force_notice',
            'type' => 'checkbox',
            'config' => 'compress.js.force',
        ],
    ];


// EOF
