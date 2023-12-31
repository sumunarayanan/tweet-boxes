# Tweet Box

## Description

The **Tweet Box** WordPress plugin allows users to easily connect to Twitter by providing their Twitter handle, key, and secret. The plugin generates a Bearer Token by conducting authentication with Twitter API. Additionally, the plugin pulls the latest 3 tweets from the specified Twitter account and renders them on a WordPress page using the shortcode `[tp-tweet-boxes]`.

## Installation

1. Upload the entire `Tweet Box` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

1. Navigate to the plugin settings in the WordPress admin under "Configure Twitter." under Settings.
2. Enter your Twitter handle, key, and secret.
3. Save the settings.
4. Use the shortcode `[tp-tweet-boxes]` in any WordPress page or post where you want to display the latest 3 tweets.

## Features

- **Twitter Authentication:** Easily authenticate with Twitter by providing your Twitter handle, key, and secret.
- **Bearer Token Generation:** The plugin generates a Bearer Token for authenticating requests to the Twitter API.
- **Shortcode Integration:** Use the shortcode `[tp-tweet-boxes]` to display the latest 3 tweets from the specified Twitter account on any page or post.

## Frequently Asked Questions

1. **How do I obtain Twitter API credentials?**
   - Visit the [Twitter Developer Portal](https://developer.twitter.com/en/apps) to create a Twitter App and obtain API key and secret.

2. **Can I customize the appearance of the tweet boxes?**
   - Currently, the plugin supports basic rendering. You can customize the styles using custom CSS in your theme.

## Changelog

### 1.0.0
- Initial release.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE) file for details.
