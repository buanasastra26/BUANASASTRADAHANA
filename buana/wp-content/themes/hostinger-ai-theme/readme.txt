=== Hostinger AI theme ===
Tags: ai-theme
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.1.11
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: hostinger-ai-theme

== Description ==

Hostinger AI theme built with SEO and mobile responsiveness in mind, this theme ensures optimal performance and a better online presence.

== Changelog ==

2.1.11 (2026-09-24)

- Feature: Repaint the generated logo for the site palette, keeping one copy
- Fix: Duplicated images generated in website sections

2.1.10 (2026-09-22)

- Feature: Prefill contacts email with the WP admin email
- Fix: AI Builder no longer opens the prompt screen with a false "5 versions will be deleted" warning when re-entering a site
- Fix: Re-entering the AI Builder menu on a generated site opens the preview screen with all previous versions, instead of restarting the generation flow
- Fix: The version limit warning now shows the real number of versions instead of a hardcoded 5
- Fix: Ed AI website generation failing with a job mismatch error
- Fix: Critical error on blog creation
- Feature: Screenshots functionality

2.1.9 (2026-09-16)

- Fix: AI Engine Experiment

2.1.8 (2026-09-16)

- Fix: Long website descriptions with accented characters or emoji no longer fail with an invalid parameter error
- Fix: Website generation no longer sends duplicate requests
- Fix: Refreshing the page during generation resumes the running generation instead of failing
- Fix: A generation interrupted before its id was stored is picked up again instead of being lost
- Fix: Returning to the setup screen no longer cancels a generation that is still running
- Fix: A start that never reached the generation service no longer counts against the retry limit
- Feature: Purge CDN cache on website generation

2.1.7 (2026-09-14)

- Fix: Cap website generation retries at 5 and strip newlines from the description
- Fix: Woocommerce plugin not installed

2.1.6 (2026-09-10)

- Fix: Rate button icon
- Feature: Template part visibility
- Feature: Update hComponents
- Feature: Hostinger Ecommerce Integration
- Fix: Preview screen opens with the generated colours and fonts

2.1.5 (2026-09-09)

- Fix: Bug fixes

2.1.4 (2026-09-08)

- Feature: AB Test for New Builder Engine

2.1.3 (2026-08-25)

- Fix: WooCommerce Activation issues
- Feature: Update chat animation and simplify logic
- Feature: Multiple versions

2.1.2 (2026-08-20)

- Tweak: Bump Tested Up to 7.1
- Tweak: Update dependencies
- Feature: Add Brand CTA to hero sections

2.1.1 (2026-08-11)

- Fix: Hero portfolio 7 missing social icons
- Fix: Plugin install details to Amplitude
- Feature: Update customisation tabs design
- Fix: Tone misspelled
- Feature: Update translations

2.1.0 (2026-07-30)

- Feature: Dark mode support
- Feature: Update website generation error handling with retry action

2.0.33 (2026-07-28)

- Fix: Darken placeholder color in the initial prompt
- Fix: Elementor empty pages
- Fix: First generation default color palette

2.0.32 (2026-07-23)

- Fix: Prevent autoloading ai version option
- Fix: Elementor About Us block

2.0.31 (2026-07-21)

- Fix: Litespeed cache options set to init once
- Feature: Add theme version to amplitude failure events
- Fix: Improve paddings in Mobile version
- Fix: Security dependencies
- Fix: Styles not loaded when contact form loaded via shortcode
- Fix: Customer review images
- Fix: Disable site-options caching

2.0.30 (2026-07-09)

- Fix: Header style issues
- Feature: Preserve state for Contacts and Social sections
- Fix: Broken block preview in Gutenberg editor
- Fix: Loader on 'Setup in WP admin' button is vertically misaligned
- Tweak: Apply global settings and border radius to all images
- Feature: Increase max words for contact us blocks
- Fix: Footer not translated
- Feature: Agenda section
- Fix: Increase contact us image dimensions
- Feature: Hide site title when logo is present
- Fix: Contact form different font placeholder and submit button style
- Feature: Remove subscription block
- Tweak: Update translations

2.0.29 (2026-07-07)

- Tweak: Translate AI menu post title
- Tweak: Update dependencies
- Fix: AI Menus Duplication when re-creating the site

2.0.28 (2026-06-30)
- Feature: Cleanup of previous data
- Feature: Update translations

2.0.27 (2026-06-25)
- Version bump: PATCH

2.0.26 (2026-06-23)
- Feature: Socials customization
- Fix: About Us Images not being replaced by AI on site creation

2.0.25 (2026-06-18)
- Feature: Update translations
- Feature: Prevent duplicate links within button sections
- Fix: Color picker margin
- Feature: Add contacts customisation
- Tweak: Preview site UI
- Fix: Issue recreating sites

2.0.24 (2026-06-16)
- Fix: Logo and title gap

2.0.23 (2026-06-11)
- Fix: Container Paddings
- Feature: Update website build tracking
- Fix: Default colors not working
- Fix: Missing translations
- Feature: Improve hero section video accuracy
- Fix: Duplicated indexes in several sections
- Tweak: Update dependencies
- Feature: Preserve previous choices (Editor, Language, Brand name) when recreating a site after initial generation

2.0.22 (2026-06-04)
- Tweak: Update dependencies
- Fix: CTA link in Hero section
- Fix: Duplicated sections
- Fix: Loading state in Enhance by AI button

2.0.21 (2026-06-02)
- Tweak: Update dependencies

2.0.20 (2026-06-01)
- Fix: Software id endpoint issue
- Fix: Color customization layouts are broken
- Fix: Fonts layout is broken in Vibe Kodee
- Fix: Reach form ID issue
- Fix: Broken loader on submit button in Vibe Kodee
- Fix: 'Add' button broken

2.0.19 (2026-05-22)
- Fix: Chatbot styles and website type improvements
- Tweak: Updated dependencies

2.0.18 (2026-05-21)
- Fix: Button and input styles
- Fix: Fallback hero video section url
- Fix: Website type detection

2.0.17 (2026-05-18)
- Fix: Software ID resolution
- Feature: New Hero Portfolio sections for Elementor
- Feature: New Service sections for Elementor
- Feature: New Contact Sections for Elementor
- Feature: New Booking section for Elementor
- Feature: Add new Customer Reviews sections for Elementor
- Tweak: Update dependencies
- Fix: Map widget in preview screen

2.0.16 (2026-05-14)
- Fix: Add fallback colors
- Feature: Add new FAQ sections
- Feature: Add new About us sections
- Feature: Added product creation retry actions
- Feature: Automatically detect brand name and display suggestions
- Tweak: Update dependencies

2.0.15 (2026-05-12)
- Tweak: Remove unnecessary tracking event
- Fix: Remove not needed attribute
- Feature: Added confirmation popup

2.0.14 (2026-05-07)
- Feature: Update dependencies
- Feature: Hotel List Sections 1,2 and 3 for Elementor
- Feature: Add domain overrides
- Feature: Move editor and site language to chatbot
- Feature: New Travel sections  1 and 2 for Elementor
- Feature: Ticket list sections 1 and 2 for Elementor
- Feature: New Real estate sections for Elementor
- Feature: Updated translations

2.0.13 (2026-05-05)
- Feature: Add brand and website type endpoints
- Fix: Update visuals for 'Continue to setup' section for mobile device

2.0.12 (2026-04-29)
- Fix: Incorrect icon size on firefox
- Fix: Disable cache for logged-in users
- Fix: Font not being applied in Blog Posts and Contact form
- Fix: Reach Form not created correctly
- Feature: Unhide website creation admin page
- Feature: New gallery sections
- Feature: New Booking sections

2.0.11 (2026-04-23)
- Fix: Hero section "Learn more" text not replaced with AI generated content
- Fix: Finish customization button wrap
- Fix: Remove selector label wraping

2.0.10 (2026-04-21)
- Fix: Services titles are not AI populated
- Fix: Code duplication on amplitude events (#642)
- Fix: Landing page website type
- Feature: New Review sections
- Fix: Refactor Hardcoded Elementor version (#638)
- Feature: Added pages customisation step
- Fix: Missing brand name
- Feature: Added translations
- Fix: Review load twice
- Fix: Skip input field rendering when brand is prefilled
- Feature: New About Us sections
- Fix: Allow dex-automation as valid branch name (#639)
- Feature: Add amplitude events and bring back scam detect (#617)

2.0.9 (2026-04-14)
- Feature: Add new Subscription sections
- Feature: Add new project sections
- Feature: Add all WordPress supported languages
- Fix: Prevent 504 error for clients
- Feature: Date format based on language
- Fix: Change AI Chat to Chat
- Feature: Hero for India sites
- Rename "My own font pair" to "Your own font pair"
- Feature: Update translations

2.0.8 (2026-04-09)
- Fix: Kodee state after recreation (#610)
- Feature: Add new Hero Sections
- Fix: Missing custom fonts online store (#608)
- Fix: font preview (#607)
- Fix: Color preserve (#606)
- Fix: Preselect font (#605)
- Fix: State of colors and fonts
- Feature: Preserve whole chatbot history (#583)
- Feature: Connect fonts to AI customization (#559)
- Feature: Add Hero for Services section
- Fix: Update brand name input placeholder (#599)
- Fix: Disable input clear action (#597)
- Feature: Exist to wp for all (#596)
- Fix: Add summary finished event (#595)
- Feature: improved iframe load time
- Tweak: Update translations
- Dev: Fix tests Workflow
- Fix: Get real domain instead of preview (#584)
- Feature: Add current color & logo state (#556)

2.0.7 (2026-04-03)
- Tweak: Update dependencies
- Tweak: Translations
- Fix: Iframe reload same href
- Fix: Pre-generate Elementor CSS after cache clear during build
- Fix: Set site creation language same as wordpress
- Fix: My account page spacing
- Fix: Hero subtitles
- Fix: Show mobile menu in Tablet view when WooCommerce is installed
- Fix: Hide survey after 5s
- Fix: Move mobile burger menu to the right
- Fix: Don't ask brand name twice
- Fix: Color codes in during your own color selection
- Fix: Amplitude events
- Fix: Fallback for default description
- Fix: Batch ecommerce product sections for unique AI-generated names
- Dev: Release automations

2.0.5 (2026-03-26)
- Feature: New service sections
- Feature: Remove store location
- Feature: Vibe kodee mobile view
- Feature: Update translations

2.0.4 (2026-03-24)
- Feature: Frame zoom
- Feature: Added contact sections
- Fix: Navigation menu transparent
- Fix: Update dependencies
- Fix: Update translations for contact
- Feature: Update language names
- Feature: Animated placeholder
- Feature: Update ai first experiment view
- Feature: Ai first flow: Add amplitude event

2.0.3 (2026-03-18)
- Feature: Color configuration update
- Feature: Added hero portfolio sections
- Feature: Hero image filtering
- Feature: Summary finished event
- Feature: Product categories sections
- Fix: Updated dependencies
- Fix: aiStore issue
- Fix: Fonts issue
- Fix: Gutenberg welcome screen
- Fix: Store location dropdown

2.0.2 (2026-03-13)
- Fix: Button not clickable after e-commerce
- Tweak: Update dependencies

2.0.1 (2026-03-11)
- Feature: Disable Elementor popups
- Feature: Changable fonts in Elementor
- Feature: AI first redirect banner
- Feature: Adjust ecommerce buttons links logic
- Fix: Limit navigation
- Feature: Colors more visible
- Feature: Update prompt input state
- Feature: Color palettes loader
- Feature: Update main loader

2.0.0 (2026-03-05)
- Feature: Blocks cleanup
- Feature: Update hero video sections
- Fix: Add to cart
- Feature: Limit prompt length
- Feature: Vibe Kodee UI

1.2.7 (2026-03-03)
- Feature: Enable multiple website types
- Feature: AI theme: second iteration
- Fix: Enhance with AI enabled
- Fix: sections and pages creation from gutenberg block

1.2.6 (2026-02-26)
- Feature: Improved hero sections
- Fix: Fixed video hero sections

1.2.5 (2026-02-24)
- Feature: Added new translations
- Feature: Added scroll animation on elementor sections
- Feature: Plugin install retry
- Feature: Added builder failure event
- Feature: Added video hero section

1.2.4 (2026-02-19)
- Fix: Terms and conditions page in navbar
- Feature: Implement scam detector for AI builder

1.2.3 (2026-02-17)
- Feature: Add FAQ section
- Fix: Not visible footer menu

1.2.2 (2026-02-12)
- Feature: Add prefill params
- Feature: Switch to API-based brand and website type detection
- Fix: Text overlay update
- Feature: Add builder amplitude failure event
- Fix: Input sizing from hComponents

1.2.1 (2026-02-10)
- Fix: Missing amplitude event
- Fix: Minor visual issues
- Fix: Survey button not visible
- Fix: Amplitude events not working from backend

1.2.0 (2026-02-05)
- Feature: Disable object cache in wp-admin
- Fix: E2E Early Access Tests workflow
- Fix: Code automation with dex
- Feature: Add hide title option to generated pages
- Feature: New ai theme design

1.1.10 (2026-01-22)
- Fix: Prevent duplicate images and empty URLs in WP Builder
- Fix: Dependency issues
- Feature: Add translations to Elementor contact widget
- Feature: Contact Form integration with Reach

1.1.9 (2026-01-06)
- Fix: WP_Upgrader fatal error
- Fix: Blog posts language translation issue
- Fix: Set correct default language code

1.1.8 (2025-12-19)
- Fix: Fatal error generating AI content

1.1.7 (2025-12-18)
- Fix: Fatal error when generating blog posts

1.1.6 (2025-12-18)
- Feature: Add Hero with form blocks
- Feature: Add SEO meta tags for pages
- Feature: Add parallax to Blocks with Background images
- Tweak: Update hComponents to 14.2.10
- Fix: Elementor gallery broken

1.1.5 (2025-12-16)
- Tweak: Implement PHP Compat WP
- Feature: Website creation experience improvements
- Feature: Updated hComponents library
- Fix: Testimonial image sizes
- Feature: Update translations

1.1.4 (2025-12-11)
- Tweak: Increase Prompt character limit
- Fix: Word Spacing issues in Japanese
- Fix: Typography Styling bugs

1.1.3 (2025-12-02)
- Feature: Bump WP Tested up to flag

1.1.2 (2025-11-20)
- Fix: Empty circle icon

1.1.1 (2025-11-20)
- Fix: Logo and title area too small
- Fix: Blank padding when site header is disabled
- Feature: Update hComponents version
- Fix: Security issues

1.1.0 (2025-11-17)
- Feature: Added Online store website type support
- Feature: Added Portfolio store website type support
- Feature: Added Landing page store website type support
- Feature: Implemented Font Combinations
- Fix: Section creation from gutenberg block
- Dev: Refactored gutenberg blocks build flow
- Dev: Fixed outdated dependencies

1.0.62 (2025-11-04)
- Feature: Added Elementor blog website type support

1.0.61 (2025-10-28)
- Fix: Service section layout

1.0.60 (2025-10-23)
- Tweak: Add Elementor food menu sections

1.0.59 (2025-10-14)
- Feature: Added Elementor gallery and resume sections
- Dev: Updated readme

1.0.58 (2025-10-09)
- Feature: Added Elementor Contact Form widget and new contact us blocks
- Fix: Unsupported builders in Surveys

1.0.57 (2025-10-07)
- Feature: Added Elementor projects sections
- Feature: Added Elementor locations sections
- Feature: Added Elementor customer review sections
- Feature: Added Elementor about us sections
- Feature: Added Elementor hero sections

1.0.56 (2025-09-25)
- Feature: Elementor integration
- Dev: Increment Unit Test coverage for Frontend
- Tweak: Added sticky menu and active menu item

1.0.55 (2025-09-08)
- Fix: Optimized testimonial images
- Dev: Dependency updates

1.0.54 (2025-09-08)
- Dev: Deployment workflows improvements
- Dev: Dependency updates
- Feature: Improved AI websites image generation
- Fix: Navigation style issues
- Feature: Don't create blog posts for online store website type

1.0.53 (2025-08-18)
- Feature: Hostinger reach plugin integration
- Fix: Remove required pages parameter
- Fix: Potential type error

1.0.52 (2025-08-12)
- Improved customer reviews images

1.0.51 (2025-07-31)
- Adjusted languages in language selector

1.0.50 (2025-07-29)
- Package update
- Fixed modal responsivness

1.0.49 (2025-07-22)
- Fix css styles in block editor
- Refactor contact forms

1.0.48 (2025-07-10)
- Added new hotel room list sections
- Added new travel destination list sections

1.0.47 (2025-07-07)
- Added ticket list sections

1.0.46 (2025-07-04)
- Fixed add shop products

1.0.45 (2025-07-03)
- Fixed alt text for cover images
- Fixed shop archive page footer
- Added new real estate sections
- Improved AI description templates
- Improved AI prompt logic

1.0.44 (2025-06-26)
- Fixed wrong section selection
- Added main landmark to frontpage template
- Added random links to CTAs
- Added alt text for images
- Fixed color contrast accessibility issues

1.0.43 (2025-06-20)
- Fixes for Ecommerce website type
- Added language selector
- New product category variations
- Fixed updates theme updates
- Updated translations
- Added new sections for AI builder

1.0.42 (2025-06-12)
- Updated WooCommerce page styles
- Added product archive template
- Enqueued assets conditionally to load only on relevant admin/pages
- Added Amplitude events for Prompt Enhancer tracking
- Show product thumbnails on Cart and Checkout pages

1.0.41
- AI Builder: prompt assistant UI

1.0.40
- Added more fonts
- Removed Hostinger social links
- Added WooCommerce integration

1.0.39
- Tweaked theme style
- Updated block structure

1.0.38
- Added correlation ID

1.0.37
- Fix edit site button URL

1.0.36
- Update footer copy

1.0.35
- Add booking website type
- Improved requests handling

1.0.34
- Update to newest hcomponents version and color palette

1.0.33
- Japanese text segmentation support

1.0.32
- Added landing page support

1.0.31
- Updated packages

1.0.30
- Fixed fonts load

1.0.29
- Added Noto Sans font for Japanese locale

1.0.28
- Add website type to Amplitude events

1.0.27
- Add back button after website generation

1.0.26
- Save selected website type

1.0.25
- Change the sequence of website type selection

1.0.24
- More relevant images

1.0.23
- Remove preselect for website type

1.0.22
- Add affiliate marketing website support

1.0.21
- Changed updates environment

1.0.20
- Fixed missing icons

1.0.19
- Prevent from duplicating blog posts titles
- Add SEO metadata for blog posts

1.0.18
- Added contact form additional variations

1.0.17
- Fixed missing icons

1.0.16
- Added contact form

1.0.15
- Add default website type selection

1.0.14
- Improved generated gradients
- Blog post external image changes

1.0.13
- Blog post functionality
- Add customer review variations

1.0.12
- Remove beta label
- Rotate used images once all of them are used for specific keyword
- Locale fixes

1.0.11
- Add gallery block
- Add projects variations
- Add our location variation
- Add our services variations
- Add scroll animation
- Add back button to onboarding

1.0.10
- Validation message fix
- Add my background block
- Add about us block variations

1.0.9
- Move style to SCSS
- Added new hero block variations
- Mobile optimizations
- Add logo to header block
- Add food menu section
- Adjust survey logic

1.0.8
- Added forced redirect to builder prompt
- Translations

1.0.7
- Fixed edit site button URL

1.0.6
- Added CSAT survey
- Hide preview domain topbar in website preview
- Add cache bypass query string to preview link

1.0.5
- Link pointer fix on hover
- Translations

1.0.4
- Translate static text in blocks
- Randomize pre-selected images
- Block styling fix

1.0.3
- Menu item changes
- Added lang param to content generation
- CSS changes
- Added default keywords for image elements

1.0.2
- Removed iframe while previewing website
- Added deletion endpoints for clearing generated site data
- Added translations

1.0.1
- Theme readme
- Add AI logic in theme
- Add localization

1.0.0
- Initial theme functionality
