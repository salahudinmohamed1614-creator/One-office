<?php
/**
 * Content types managed by admin/manage.php.
 * Field types: text, textarea, lines, number, price, icon, image, checkbox, rating, select
 */
return [
    'categories' => [
        'title' => 'Service categories', 'singular' => 'Category', 'icon' => 'bi-collection',
        'delete_warning' => 'Deleting a category also deletes all services inside it. Continue?',
        'fields' => [
            'icon'        => ['label' => 'Icon', 'type' => 'icon', 'required' => true, 'default' => 'bi-folder2-open',
                              'help' => 'Bootstrap Icons name, e.g. bi-buildings, bi-mortarboard, bi-airplane (icons.getbootstrap.com)'],
            'title'       => ['label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 150],
            'subtitle'    => ['label' => 'Subtitle (e.g. English name)', 'type' => 'text', 'max' => 150],
            'description' => ['label' => 'Description', 'type' => 'textarea', 'max' => 1000],
            'sort_order'  => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
            'is_active'   => ['label' => 'Visible on website', 'type' => 'checkbox', 'default' => 1],
        ],
        'list' => ['icon', 'title', 'subtitle', 'sort_order', 'is_active'],
    ],
    'services' => [
        'title' => 'Services', 'singular' => 'Service', 'icon' => 'bi-grid',
        'order' => 'category_id, sort_order, id',
        'fields' => [
            'category_id' => ['label' => 'Category', 'type' => 'select', 'required' => true,
                              'options_sql' => 'SELECT id, title FROM categories ORDER BY sort_order, id'],
            'icon'        => ['label' => 'Icon', 'type' => 'icon', 'required' => true, 'default' => 'bi-file-earmark-text',
                              'help' => 'Bootstrap Icons name, e.g. bi-file-earmark-check, bi-passport, bi-receipt'],
            'title'       => ['label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 150],
            'description' => ['label' => 'Short description', 'type' => 'textarea', 'max' => 1000],
            'items'       => ['label' => 'Details / bullet points (one per line, optional)', 'type' => 'lines', 'max' => 2000],
            'sort_order'  => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
            'is_active'   => ['label' => 'Visible on website', 'type' => 'checkbox', 'default' => 1],
        ],
        'list' => ['icon', 'title', 'category_id', 'sort_order', 'is_active'],
    ],
    'faqs' => [
        'title' => 'FAQ', 'singular' => 'Question', 'icon' => 'bi-question-circle',
        'fields' => [
            'question'   => ['label' => 'Question', 'type' => 'text', 'required' => true, 'max' => 255],
            'answer'     => ['label' => 'Answer', 'type' => 'textarea', 'required' => true, 'max' => 3000],
            'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
            'is_active'  => ['label' => 'Visible on website', 'type' => 'checkbox', 'default' => 1],
        ],
        'list' => ['question', 'sort_order', 'is_active'],
    ],
    'testimonials' => [
        'title' => 'Testimonials', 'singular' => 'Testimonial', 'icon' => 'bi-chat-quote',
        'fields' => [
            'name'       => ['label' => 'Customer name', 'type' => 'text', 'required' => true, 'max' => 100],
            'company'    => ['label' => 'Company / title', 'type' => 'text', 'max' => 100],
            'quote'      => ['label' => 'Quote', 'type' => 'textarea', 'required' => true, 'max' => 1000],
            'rating'     => ['label' => 'Rating', 'type' => 'rating', 'default' => 5],
            'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0],
            'is_active'  => ['label' => 'Visible on website', 'type' => 'checkbox', 'default' => 1],
        ],
        'list' => ['name', 'company', 'rating', 'is_active'],
    ],
];
