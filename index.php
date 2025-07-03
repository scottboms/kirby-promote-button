<?php

load(['scottboms\Promote\PlatformPromoter' => __DIR__ . '/classes/PlatformPromoter.php']);

use ScottBoms\Promote\PlatformPromoter;
use Composer\Semver\Semver;
use Kirby\Http\Remote;
use Kirby\Cms\App as Kirby;

// validate Kirby version
if (Semver::satisfies(Kirby::version() ?? '0.0.0', '~5.0') === false) {
	throw new Exception('Promoter Button requires Kirby 5');
}

Kirby::plugin('scottboms/promote', 
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
                        return [
                          'value' => $service,
                          'text' => $service
                        ];
                      }, option('scottboms.promote.services', []))
                    ],
                  ],
                  'value' => [
                    'text' => 'Something new "' . $page->title()->value() . '": ' . $page->url(),
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
    'version' => '1.0.0',
    'license' => 'MIT',
    'authors' => [
      [
        'name' => 'Scott Boms'
      ]
    ]
  ]
);