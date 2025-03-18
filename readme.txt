=== WordPress Word Markup Cleaner ===
Contributors: prittenhouse
Tags: word, markup, cleaner, content, editor, formatting, microsoft, office, html
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 3.5
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically cleans Microsoft Word markup from content when saved in WordPress, preserving structure while removing unnecessary formatting.

== Description ==

WordPress Word Markup Cleaner automatically cleans Microsoft Word markup and formatting from your content when it's saved in WordPress. It helps maintain clean, consistent HTML throughout your site without manual editing.

### Why You Need This Plugin

When content creators copy and paste text from Microsoft Word into WordPress, it brings along a lot of unnecessary HTML markup that can:

* Bloat your pages and slow down your site
* Cause inconsistent styling that breaks your site's design
* Create unexpected formatting and layout issues
* Make future content edits difficult

This plugin serves as a safety net for your content team, automatically removing problematic Word-specific code while preserving the important structural elements like tables and lists.

### Key Features

* **Automatic Cleaning:** Removes Word markup as content is saved
* **DOM Processing:** Uses an intelligent DOM-based processing engine for efficient cleaning
* **Structure Preservation:** Maintains tables and lists while removing unnecessary formatting
* **ACF Integration:** Works with Advanced Custom Fields (text, textarea, WYSIWYG, repeater, flexible content)
* **Debug Logging:** Optional debug mode to monitor and troubleshoot the cleaning process
* **Extensive Flexibility:** Configurable settings to adapt to your specific needs
* **High Performance:** Optimized for large content with caching and chunked processing

### How It Works

When content is saved in WordPress, the plugin detects Microsoft Word markup and removes:

The plugin offers two processing methods:

* **DOM-based Processing (Default):** Efficiently processes only elements containing Word markup, preserving elements that don't need cleaning
* **Legacy Regex Processing:** Provides fallback compatibility for complex content structures

### What Gets Cleaned

* Microsoft Office XML namespace tags and attributes
* Word-specific conditional comments
* MSO class attributes and inline styles
* Font and styling attributes that override your theme
* Empty spans and other unnecessary elements

### Benefits

* Cleaner HTML output with up to 70% reduction in unnecessary code
* Consistent styling across your entire website
* Improved page load performance
* Better compatibility with theme styling
* Less headaches for developers and content editors

### Installation

1. Upload the `wordpress-word-markup-cleaner` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools > Word Markup Cleaner to configure settings
4. That's it! The plugin will automatically clean Word markup when content is saved

### Frequently Asked Questions

= Will this plugin affect my existing content? =

No, the plugin only cleans content when it's saved. Your existing content will remain unchanged until you edit and save it again.

= Does it work with Advanced Custom Fields? =

Yes! The plugin includes full support for ACF fields, including text, textarea, WYSIWYG fields, and even complex field types like repeaters and flexible content.

= Will it remove important formatting like tables and lists? =

No, the plugin is designed to preserve the structure of tables and lists while only removing the unnecessary Word-specific markup.

= How can I tell if it's working? =

The plugin includes a "Test Cleaner" feature in the settings page where you can paste Word content and see exactly what changes will be made.

= Does it slow down my site? =

No, the plugin only runs when content is saved, not when pages are displayed to visitors. The cleaning process is very efficient and won't noticeably affect the editing experience.

= Can I customize what gets cleaned? =

Yes, the plugin includes options to enable/disable content cleaning, ACF field cleaning, and offers granular control over cleaning operations for each content type.

= Is there a way to see what changes are being made? =

Yes, you can enable debug logging in the plugin settings to keep a detailed log of all cleaning operations.

### Screenshots

1. Plugin overview
[About]: https://ps.w.org/wordpress-word-markup-cleaner/assets/img/screenshot-about.png

2. Settings page with configuration options
[Settings]: https://ps.w.org/wordpress-word-markup-cleaner/assets/img/screenshot-settings.png

2. ACR field type configuration options
[ACF Settings]: https://ps.w.org/wordpress-word-markup-cleaner/assets/img/screenshot-acf-settings.png

3. Test cleaner interface showing before/after content
[Test Cleaner]: https://ps.w.org/wordpress-word-markup-cleaner/assets/img/screenshot-test-cleaner.png

4. Debug log viewer for detailed insights
[Debug Log]: https://ps.w.org/wordpress-word-markup-cleaner/assets/img/screenshot-debug-log.png

### Changelog

= 3.5 =
* Added DOM-based processing engine for more efficient cleaning
* Added centralized settings management for improved plugin architecture
* Added advanced caching mechanism for content type settings
* Enhanced caching of cleaning settings for better performance
* Added rate limiting to log operations for enhanced security
* Fixed compatibility issues with some WordPress configurations
* Improved memory usage when processing large content blocks
* Enhanced error handling for more robust operation
* Fixed display escaping in test cleaner results
* Improved regex pattern matching for Word markup detection in test cleaner
* Added tab-based interface for improved navigation

= 3.4 =
* Significantly improved performance for large content blocks
* Added content processing in chunks to reduce memory usage
* Added smart early detection of Word content
* Implemented multi-level caching system
* Enhanced font family detection and removal
* Added 'Strip All Styles' option for complete style attribute removal
* Improved error handling for regex operations
* Optimized table and list processing

= 3.3.2 =
* Added centralized clipboard utility for improved code maintainability
* Optimized debug log detailed changes functionality
* Refactored duplicate code patterns for better performance
* Enhanced cross-browser clipboard functionality
* Improved notification system with customizable position and duration
* Added fallback mechanisms for clipboard operations

= 3.3.1 =
* Improved HTML escaping in debug logger for enhanced security
* Updated jQuery implementation in admin settings for better consistency
* Enhanced notification system using jQuery animations
* Optimized code structure and organization

= 3.3 =
* Added support for ACF Blocks in Gutenberg editor
* Improved block content cleaning with caching for better performance
* Added compatibility with the WordPress REST API for Gutenberg
* Added version detection for ACF Blocks (requires ACF 5.8.0+)
* Enhanced admin UI to show ACF Blocks support
* Added validation to prevent breaking block structures during cleaning

= 3.2 =
* Added targeted ACF field type cleaning for better performance
* Added field type configuration UI in plugin settings
* Optimized processing for complex nested ACF fields
* Improved handling of large repeater and flexible content fields

= 3.1 =
* Added version tracking for better upgrade management
* Converted regex patterns to constants for improved maintainability
* Implemented content-type specific cleaning for optimized processing
* Added configurable cleaning levels for different content types
* Enhanced support for custom post types
* Added excerpt-specific processing with HTML stripping option
* Improved admin interface with content type configuration

= 3.0 =
* Complete refactoring of the plugin architecture for better maintainability
* Added advanced debug logging with rotation and management features
* Enhanced ACF integration with support for more field types
* Improved table and list structure preservation
* Added detailed test cleaner with visualization of removed elements
* Optimized cleaning algorithms for better performance
* Added multisite support

= 2.5 =
* Added support for ACF flexible content fields
* Improved handling of nested structures
* Better compatibility with WordPress 6.0+

= 2.0 =
* Added Advanced Custom Fields integration
* Improved table structure preservation
* Added test cleaner feature

= 1.5 =
* Enhanced list cleaning and preservation
* Better handling of Word conditional comments
* Fixed issues with nested tags

= 1.0 =
* Initial release

### Upgrade Notice

= 3.5 =
Major architecture update that improves settings management, adds DOM-based processing for more efficient cleaning, adds advanced caching for cleaning settings to enhances performance, and adds rate limiting to log operations for improved security. Fixes compatibility issues with certain WordPress configurations, including an improved test cleaner display and more accurate Word markup detection.

= 3.4 =
Major performance update with optimized processing for large content blocks, smart caching, improved font handling, and memory usage reduction. Adds "Strip All Styles" option for complete removal of style attributes.

= 3.3.2 =
Maintenance update that improves code organization and fixes clipboard functionality issues. Introduces a centralized clipboard utility and optimized logging for better performance and maintainability.

= 3.3.1 =
Security and performance patch that improves HTML escaping in debug logs and enhances admin UI with better jQuery implementation.

= 3.3 =
Important update adds support for ACF Blocks in Gutenberg editor, improving compatibility with modern WordPress site building. Includes performance optimizations for block content cleaning.

= 3.2 =
Added targeted ACF field type cleaning for better performance, field type configuration UI, and optimized processing for complex nested ACF fields.

### Development

The plugin is maintained on GitHub. Pull requests and bug reports are welcome:

[GitHub Repository](https://github.com/pdrittenhouse/wp-content-cleaner)

### Credits

* Original concept by P.D. Rittenhouse

### Acknowledgments

* Icon by [Freepik](https://www.freepik.com) from [Flaticon](https://www.flaticon.com/)
