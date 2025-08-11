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

Kirby::plugin('scottboms/promote-button', [

	'areas' => [
		'site' => function () {
			return [
				'buttons' => [
					'promote' => function ($page) {
						return [
							'icon'   => 'megaphone',
							'text'   => 'Promote',
							'theme'  => 'pink-icon',
							'title'  => 'Share this Page',
							'dialog' => 'promote/?page=' . $page->uuid()->toString(),
						];
					},

					'profiles' => function () {
						$services = option('scottboms.promote.services', []);
						$items = [];

						foreach ($services as $service) {
							if ($service === 'mastodon') {
								$host = trim((string) option('scottboms.promote.mastodon.url'));
								$user = trim((string) option('scottboms.promote.mastodon.username'));
								if ($host && $user) {
									$items[] = [
										'text'   => 'Mastodon',
										'icon'   => 'mastodon', // keep generic to rule out custom icon issues
										'link'   => 'https://' . $host . '/@' . $user,
										'target' => '_blank',
									];
								}
							} elseif ($service === 'bluesky') {
								$handle = trim((string) option('scottboms.promote.bluesky.handle'));
								if ($handle) {
									$items[] = [
										'text'   => 'Bluesky',
										'icon'   => 'bluesky',
										'link'   => 'https://bsky.app/profile/' . $handle,
										'target' => '_blank',
									];
								}
							} elseif ($service === 'linkedin') {
								$username = trim((string) option('scottboms.promote.linkedin.username'));
								if ($username) {
									$items[] = [
										'text'   => 'LinkedIn',
										'icon'   => 'linkedin',
										'link'   => 'https://linkedin.com/in/' . $username,
										'target' => '_blank',
									];
								}
							}
						}

						if (!$items) {
							$items[] = [
								'text'     => 'No profiles configured',
								'icon'     => 'alert',
								'disabled' => true,
							];
						}

						return [
							'component' => 'k-profiles-button',
							'props' => [
								'text'     => 'Profiles',
								'icon'     => 'account',
								'theme'    => 'pink-icon',
								'title'    => 'Profiles',
								'items'    => $items,
							],
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
											'columns' => max(1, min(3, count(option('scottboms.promote.services', [])))),
											'options' => array_map(function ($service) {
												$labelMap = [
													'mastodon' => 'Mastodon',
													'bluesky'  => 'Bluesky',
													'linkedin' => 'LinkedIn',
												];
												return [
													'value' => $service,
													'text'  => $labelMap[$service] ?? ucfirst($service)
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
										'platforms' => ['mastodon','bluesky','linkedin'],
									],
									'submitButton' => [
										'icon'  => 'megaphone',
										'text'  => 'Post',
										'theme' => 'pink'
									],
								],
							];
						},
						'submit' => function () {
							$text = get('text');
							$enabled = option('scottboms.promote.services', []);
							$platforms = array_intersect(get('platforms', []), $enabled);
							if (!is_array($platforms)) return false;

							$promoter = new ScottBoms\Promote\PlatformPromoter();
							foreach ($platforms as $platform) {
								$promoter->post($platform, $text);
							}
							return true;
						},
					],
				],
			];
		},
	],

	'info' => [
		'homepage' => 'https://github.com/scottboms/kirby-promote-button',
		'version'  => '1.1.0',
		'license'  => 'MIT',
		'authors'  => [[ 'name' => 'Scott Boms' ]],
	],

]);
