=== Plugin Name ===
Tags: AI, AI assistant
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: 3.1.1
Requires at least: 5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Hostinger AI: Effortlessly create engaging content using AI. Streamline content generation for blogs, articles, and more.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `hostinger-ai-assistant.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

== Changelog ==

3.1.1 (2026-09-24)

- Fix: AI assistant sidebar reopening

3.1.0 (2026-09-22)

- Feature: New MCP tools architecture
- Fix: Ed the Kodee nudge suggesting to connect Reach on sites that are already connected.
- Fix: Ed AI Content Creator block losing the generated content when clicking "Use this content".
- Feature: SEO metadata MCP tools
- Fix: Ed a security issue where preview-mode media URLs could be rewritten to an external host and cached.
- Feature: Hide chatbot welcome screen on Hub
- Feature: Update resource limits nudge cta in chatbot widget
- Feature: Media upload MCP tool
- Fix: Harden comment content sanitization
- Tweak: Reduce MCP tool name prefix

3.0.55 (2026-09-10)

- Fix: Fonts in Agent widget

3.0.54 (2026-09-01)

- Tweak: Bump chatbot widget

3.0.53 (2026-08-25)

- Tweak: Rename Kodee to Hostinger Agent
- Feature: Prepare for global chatbot events

3.0.52 (2026-08-20)

- Tweak: Bump Tested Up to 7.1
- Fix: Update dependencies
- Fix: Open buttons in same window
- Feature: Added google site-kit nudge
- Fix: Hide chatbot in AI Builder v2

3.0.51 (2026-08-11)

- Fix: Align domain renewal nudge window
- Fix: Change domain nudge priority
- Feature: Update dependencies

3.0.50 (2026-07-28)

- Feature: Add video background support for Elementor containers
- Feature: Added hostinger reach setup nudge
- Feature: Added plan upgrade nudge
- Fix: Update images widget tool description
- Fix: Kodee menu shrink on sidebar activation
- Fix: Update page tools description
- Fix: Delete menu confirmation
- Fix: Kodee menu removal
- Feature: Update nudges show logic
- Fix: WooCommerce not installed
- Fix: Kodee page removal from menu

3.0.49 (2026-07-21)

- Fix: Translations
- Tweak: Increase JWT token max Expiring time to 1 Month
- Fix: Kodee shows welcome screen instead of nudge
- Fix: Reopening kodee window
- Fix: Kodee covers gutenberg publish button

3.0.48 (2026-07-07)

- Tweak: Update dependencies

3.0.47 (2026-07-02)

- Tweak: Update dependencies

3.0.46 (2026-06-30)

- Feature: Update translations
- Fix: DIVI builder chatbot widget
- Feature: Added woocommerce set up nudge
- Feature: Added domain renewal nudge

3.0.45 (2026-06-25)

- Fix: WP_REST_Navigation_Controller class not found

3.0.44 (2026-06-23)

- Feature: Added renew hosting plan kodee nudge

3.0.43 (2026-06-18)

- Feature: Update translations
- Feature: Add WordPress navigation MCP
- Feature: Add WooCommerce orders MCP

3.0.42 (2026-06-16)

- Fix: Hide chatbot widget in website creation page
- Feature: Support for MCP Revisions
- Feature: MCP for updating Global Style typography and colors
- Fix: Purge cache when toggling maintenance with MCP

3.0.41 (2026-06-11)

- Fix: Chatbot greeting button text color
- Feature: Add Container MCP tools
- Fix: Chatbot button adjustments

3.0.40 (2026-06-09)

- Feature: New MCP tool for listing Elementor templates
- Tweak: Update dependencies
- Feature: Add jwt cli command
- Feature: Added floating sidebar with draggable button

3.0.39 (2026-05-22)

- Fix: Chatbot-widget

3.0.38 (2026-05-19)

- Fix: Remove AI assistant top bar
- Tweak: Update dependencies

3.0.37 (2026-05-12)

- Fix: Incorrect plugin update versions in MCP tool
- Feature: Add Kodee to top bar inside editors

3.0.36 (2026-05-05)

- Feature: Exclude floating buttons cpt from content creation dropdown
- Fix: Destructive hint for Woo MCP tools
- Fix: Token generation

3.0.35 (2026-04-23)

- Feature: Added translations

3.0.34 (2026-04-16)

- Fix: Update dependencies
- Fix: Security patch

3.0.33 (2026-04-02)
- Dev: Fix Workflows for Release automation
- tweak: Update dependencies

3.0.32 (2026-03-31)
- Fix: Scope abilities registration
- Tweak: Update product description endpoint
- Tweak: Update dependencies
- Dev: Add release Automation Workflow

3.0.31 (2026-03-26)
- Feature: MCP for plugin & theme management
- Feature: Add WooCommerce coupons MCP tools

3.0.30 (2026-03-18)
- Tweak: Bump up chatbot widget version
- Tweak: Update dependencies
- Tweak: Don't load chatbot on non-Hostinger environment
- Fix: Tone of voice dropdown

3.0.29 (2026-03-05)
- Feature: Updated translations

3.0.28 (2026-02-19)
- Feature: Harden capability check

3.0.27 (2026-02-17)
- Tweak: Update dependencies

3.0.26 (2026-02-10)
- Fix: Hide chatbot widget on AI theme route
- Feature: Track MCP nudges
- Feature: Bump up chatbot widget version, add next message suggestions

3.0.25 (2026-02-03)
- Fix: Dex Code automation
- Fix: Environment variable name
- Feature: Permission check for MCP
- Feature: Convert MCP generated posts to blocks

3.0.24 (2026-01-22)
- Feature: Update chatbot widget

3.0.23 (2026-01-15)
- Tweak: Update dependencies

3.0.22 (2026-01-13)
- Feature: Chatbot greetings screen
- Fix: AI Product description length
- Fix: Select input styling
- Fix: Auto starting Kodee greeting screen in onboarding
- Dev: Tweak DEX automation workflow
- Dev: Tweak E2E tests workflow files

3.0.21 (2026-01-06)
- Dev: Internal workflows

3.0.20 (2025-12-18)
- Fix: Elementor breaking MCP input schema validation
- Feature: Implement PHP Compat WP PHP 8.1
- Feature: Remove MCP consent popup
- Feature: Update translations
- Fix: Limit survey filled event
- Feature: Add Elementor MCP tools

3.0.19 (2025-12-11)
- Tweak: Bump Chatbot version

3.0.18 (2025-12-09)
- Feature: New MCP integration
- Feature: Update hComponents
- Fix: Tooltip not visible
- Fix: Added missing Rate your conversation label for Kodee

3.0.17 (2025-12-02)
- Fix: Website route permission checks
- Fix: Add missing workflow permissions
- Fix: Fatal error when no featured image is found
- Feature: Bump WP 6.9 tested up to flag
- Feature: Add code automation workflow
- Fix: Convert AI Created Post to Gutenberg Blocks
- Feature: Update kodee z-index
- Feature: Add Kodee to Elementor editor

3.0.16 (2025-11-18)
- Fix: Translations not showing up correctly
- Fix: Admin CSS conflict
- Feature: Update translations

3.0.15 (2025-11-11)
- Tweak: Bump up chatbot widget version

3.0.14 (2025-11-06)
- Feature: Update chatbot widget version

3.0.13 (2025-10-30)
- Fix: Remove duplicate token initialisation
- Refactor: Improve code structure
- Feature: Update chatbot widget version

3.0.12 (2025-10-28)
- Feature: Update chatbot widget nudges
- Feature: Update translations

3.0.11 (2025-10-21)
- Tweak: Tweak tool's error message

3.0.10 (2025-10-16)
- Fix: Chatbot welcome text

3.0.9 (2025-10-14)
- Fix: Secure Ajax requests
- Feature: Proper error handling for chatbot

3.0.8 (2025-10-09)
- Feature: Update chatbot widget version

3.0.7 (2025-10-07)
- Feature: Introduce suggestive actions chatbot
- Fix: Fixed Google Sitekit loadash conflict
- Feature: Update chatbot widget version

3.0.6 (2025-09-30)
- Feature: Enable destructive actions MCP
- Tweak: Bump dependencies
- Tweak: Security updates
- Dev: Fix Release updater

3.0.4 (2025-09-25)
- Fix: Warning on plugin activation
- Fix: Metadata structure when using Spectra
- Fix: Missing classes issue
- Fix: Unresponsive click events in Google Site Kit
- Dev: Added unit tests
- Dev: Added pull request comment template, frontend unit testing infrastructure

3.0.4 (2025-09-18)
- Fix: HTTP_ORIGIN PHP warnings
- Feature: Removed Beta badge from chatbot
- Fix: Bumped chatbot widget package version
- Fix: Changed chatbot widget label
- Feature: Added amplitude events for AI website generation

3.0.3 (2025-09-15)
- Fix: Issue with class not being found

3.0.2 (2025-09-10)
- Feature: Integration with new Chatbot Widget
- Fix: Load plugin Styles only on Hostinger Pages
- Dev: Fix Lint Workflow
- Dev: Update PR template
- Dev: Deployment workflow changes

3.0.1 (2025-08-21)
- Change menu registration priority
- Update translations
- Handle chatbot timeouts better

3.0.0 (2025-08-08)
- Added MCP functionality
- Updated translations
- Removed unused spacing

2.0.42 (2025-07-28)
- Updated translations
- Fixed Submit button freeze issue

2.0.41 (2025-07-17)
- Fixed assets version load

2.0.40 (2025-07-03)
- Updated dependencies
- Fixed chip button not centered

2.0.39 (2025-06-19)
- Renamed featured image label
- Updated translations
- Fixed updates

2.0.38 (2025-06-12)
- Remove unnecessary word capitalization in AI Content dropdown
- Tweak CSAT logic
- Bump Hostinger WP Surveys to v1.1.12
- Boot WP Surveys in the plugin

2.0.37 (2025-05-26)
- Add AI block to recently used blocks
- Fix Jetpack autoloader issues

2.0.36 (2025-04-30)
- Add correlation id

2.0.35 (2025-04-15)
- Add preview and site edit links

2.0.34 (2025-04-07)
- Remove Hardcoded references

2.0.33 (2025-03-25)
- More relevant images
- Improved internal requests
- Updated hcomponents library

2.0.32 (2025-02-21)
- Update menu package
- Added translations

2.0.31 (2025-02-11)
- Added translations
- Fixed updates

2.0.30 (2025-01-24)
- Adapted plugin to new temporary domains

2.0.29 (2025-01-14)
- Changed updates environment
- Added translations

2.0.28 (2025-01-08)
- Added preview website link in navbar

2.0.27 (2024-12-16)
- Update packages

2.0.26 (2024-12-06)
- Update packages

2.0.25 (2024-11-20)
- Translation load fix

2.0.24 (2024-11-12)
- Change preview domain url
- Updated amplitude package

2.0.23 (2024-10-25)
- Adjust survey logic

2.0.22 (2024-10-16)
- Align admin bar menu items
- Update amplitude package

2.0.21 (2024-10-10)
- Added new permissions for the Editor role.
- Fixed issue where product titles were being overwritten.
- Added a link to the AI Creator tutorial.

2.0.20 (2024-10-04)
- Fix chatbot position

2.0.19 (2024-09-18)
- Rename AI assistant

2.0.18 (2024-09-17)
- Fixed AI Gutenberg block styles in site editor

2.0.17 (2024-09-12)
- Security update

2.0.16 (2024-09-11)
- Packages update

2.0.15 (2024-09-08)
- Fixed domain validation for chatbot

2.0.14 (2024-09-02)
- Fixed translations in Gutenberg block

2.0.13 (2024-08-19)
- Add loading modal

2.0.12 (2024-07-31)
- Scope hcomponents library styling

2.0.11
- Added translations
- Fixed arabic css styles

2.0.10
- Surveys adjustments
- Translations fix

2.0.9
- Update dependencies

2.0.8
- Improved woocommerce AI product generation

2.0.7
- Remove chatbot amplitude experiment

2.0.6
- Add All in One SEO compatibility
- Additional AI content validation
- Redirection to AI content creation

2.0.5
- Updated menu package

2.0.4
- Readme change

2.0.3
- Change labels
- FR locale fix
- Fixed conflicts between packages

2.0.2
- Fixed hPanel url

2.0.1
- Added surveys and amplitude packages

2.0.0
- Added menu management package

1.8.3
- Rename plugin

1.8.2
- Security fix

1.8.1
- Chatbot improvements

1.8.0
- Increased minimum PHP version

1.7.9
- Previous version revert

1.7.8
- Added Gutenberg block for AI creation

1.7.7
- Added chatbot amplitude events

1.7.6
- Fixed loading issues

1.7.5
- Adjusted internal services

1.7.4
- Style changes

1.7.3
- Implemented chatbot

1.7.2
- Added AI image into content generation

1.7.1
- Added long text content generation

1.7.0
- Added AI product generation

1.6.9
- Css fixes
- Internal services improvements
- Text changes

1.6.8
- Fixed assets load on subfolder installations

1.6.7
- Seo optimized AI images
- Added translations
- Adjusted assets loading

1.6.6
- Fixes

1.6.5
- Fix seo metadata

1.6.4
- Font changes

1.6.3
- Text changes, translations

1.6.2
- Bugfix, improvements

1.6.1
- Added focus keywords

1.6.0
- Multiple Tone of voice selection

1.5.1
- Add additional translations

1.5.0
- Add seo meta keywords and description

1.4.3
- Bugfix

1.4.2
- Add additional request header

1.4.1
- Bugfix

1.4.0
- Added translations

1.3.1
- Style changes

1.3.0
- Added content generation for other post types
- Added content tone and length selection

1.2.3
- Bugfix

1.2.2
- Bug fixes

1.2.0
- Allow direct post publish
- Bug fixes
- Improved error handling

1.1.3
- Bug fixes

1.1.2
- Additional AI assistant buttons

1.1.1
- Preview domain fix

1.1.0
* Added AI generated post featured image

1.0.2
* Text changes

1.0.1
* Text changes

1.0.0
* Initial release
