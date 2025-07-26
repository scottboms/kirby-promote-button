<?php

load(['scottboms\Promote\PlatformPromoter' => __DIR__ . '/classes/PlatformPromoter.php']);

use ScottBoms\Promote\PlatformPromoter;
use Kirby\Http\Remote;
use Kirby\Cms\App;

// shamelessly borrowed from distantnative/retour-for-kirby
if (
	version_compare(App::version() ?? '0.0.0', '4.0.1', '<') === true ||
	version_compare(App::version() ?? '0.0.0', '6.0.0', '>=') === true
) {
	throw new Exception('Promote Button requires Kirby v4 or v5');
}

Kirby::plugin('scottboms/promote-button',
  extends: [
    'areas' => [
      'site' => [
        'buttons' => [
          'promote' => function ($page) {
            return [
              'icon' => 'megaphone',
              'text' => 'Promote',
              'theme' => 'pink-icon',
              'dialog' => 'promote/?page=' . $page->uuid()->toString(),
            ];
          },
        ],
        'dialogs' => [
          'promote' => [
            'load' => function () {
              $page = page(get('page'));

              return [
                'component' => 'k-form-dialog',
                'props' => [
                  'size' => 'large',
                  'fields' => [
                    'text' => [
                      'label' => 'Text',
                      'type' => 'textarea',
                      'buttons' => false,
                      'size' => 'small',
                      'required' => true,
                    ],
                    'platforms' => [
                      'label' => 'Post To',
                      'type' => 'checkboxes',
                      'columns' => max(1, min(3, count(option('scottboms.promote.services', [])))), // 1–3 columns
                      'options' => array_map(function ($service) {
                        $labelMap = [
                          'mastodon' => 'Mastodon',
                          'bluesky' => 'Bluesky',
                          'linkedin' => 'LinkedIn',
                          // Add more if needed
                        ];

                        return [
                          'value' => $service,
                          'text' => $labelMap[$service] ?? ucfirst($service)
                        ];
                      }, option('scottboms.promote.services', []))
                    ],
                  ],
                  'value' => [
                    'text' => 'Just posted ' . $page->title()->value() . ' ' . (
                        option('scottboms.promote.host_url')
                          ? rtrim(option('scottboms.promote.host_url'), '/') . '/' . $page->uri()
                          : $page->url()
                    ),
                    'platforms' => [
                      'mastodon',
                      'bluesky',
                      'linkedin',
                    ],
                  ],
                  'submitButton' => [
                    'icon' => 'megaphone',
                    'text' => 'Post',
                    'theme' => 'pink'
                  ],
                ],
              ];
            },
            'submit' => function () {
              $text = get('text');
              $enabledServices = option('scottboms.promote.services', []);
              $platforms = array_intersect(get('platforms', []), $enabledServices);

              if (!is_array($platforms)) {
                return false;
              }

              $promoter = new PlatformPromoter();

              foreach ($platforms as $platform) {
                try {
                  $promoter->post($platform, $text);
                } catch (Exception $e) {
                  // Optional: log error, show feedback, etc.
                  throw new Exception("Failed to post to $platform: " . $e->getMessage());
                }
              }

              return true;
            },
          ]
        ]
      ],
    ],
  ],

  info: [
    'homepage' => 'https://scottboms.com',
    'version' => '1.0.2',
    'license' => 'MIT',
    'authors' => [
      [
        'name' => 'Scott Boms'
      ]
    ]
  ]
);
