# Promote Button for Kirby

![Plugin Preview](src/assets/kirby-promote-button.png)

A customizable View Button for Kirby 5 that builds on Bastian's demo from the [Kirby 5 Release Show](https://youtube.com/watch?v=o2xkzqiLEUM) adding missing functionality and configuration settings for Mastodon, Bluesky, and LinkedIn as well as other user-experience enhancements.

## Requirements

This plugin requires Kirby 5.x and newer. It will not work with earlier versions of Kirby.

## Installation

### [Kirby CLI](https://github.com/getkirby/cli)

    kirby plugin:install scottboms/kirby-promote-button

### Git submodule

    git submodule add https://github.com/scottboms/kirby-promote-button.git site/plugins/promote-button

### Copy and Paste

1. [Download](https://github.com/scottboms/kirby-promote-button/archive/master.zip) the contents of this repository as Zip file.
2. Rename the extracted folder to `promote-button` and copy it into the `site/plugins/` directory in your project.

## Configuration

To function, the plugin requires configuration as outlined below.

### Required Settings

You can place these in `/site/config/config.php` or `/site/config/env.php`

#### General

    return [
      'scottboms.promote' => [
        'services' => [
          'mastodon', 'bluesky', 'linkedin'
        ],
        'mastodon' => [
          'url' => 'MASTODON_HOST', // e.g. https://mastodon.social
        ],
        'bluesky' => [
          'base_url' => 'BLUESKY_HOST', // e.g. https://bsky.social
          'handle' => 'USERNAME', // e.g. example.bsky.social‬
        ]
      ],
    ]

#### Tokens and Passwords

To post to [Mastodon](https://mastodon.social/settings/applications), [Bluesky](https://bsky.app/settings/app-passwords) or [LinkedIn](https://linkedin.com/developers/apps), you will need the necessary authentication tokens or app passwords. Because this information is sensitive, it's recommended that you do not include these specific settings in your `/site/config/config.php` file and instead place them in either the [env.php config file](https://getkirby.com/docs/guide/configuration#multi-environment-setup__deployment-configuration) which should be explicitly ignored by [git](https://git-scm.com) or other version control systems or .

    return [
      'scottboms.promote.mastodon.token' => 'MASTODON_API_TOKEN',
      'scottboms.promote.bluesky.password' => 'BLUESKY_APP_PASSWORD',
      'scottboms.promote.linkedin.token' => 'LINKEDIN_OAUTH_TOKEN',
    ],


### Optional Settings

If you run your Kirby site locally, the Promote button will function but page urls added to the dialog will use the local hostname (e.g. localhost) which isn't very helpful when posting to public services. You can override this behaviour by setting `host_url` in the configuration.

    'scottboms.promote' => [
      'host_url' => 'SHARED_LINK_HOST', // e.g. https://example.com
    ]

## Blueprint Configuration

There are multiple methods to add [View Buttons](https://getkirby.com/releases/5/view-buttons) to your Kirby installation. To add and configure the look of this button, it can be added to any page by adding the `buttons` [option](https://getkirby.com/docs/reference/panel/blueprints/page#view-buttons) in the page blueprint.

    buttons:
      promote: true

## Credits

* Original Concept and Starting Points: [Bastian Allgeier](https://github.com/bastianallgeier/)
* Supported Services: [Mastodon](https://mastodon.social), [Bluesky](https://bsky.app), [LinkedIn](https://linkedin.com)


## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test before using it in a production environment. If you identify an issue, typo, etc, please [create a new issue](/issues/new) so I can investigate.

## License

[MIT](https://opensource.org/licenses/MIT)
