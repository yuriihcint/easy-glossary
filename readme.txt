=== Easy Glossary ===
Plugin Name: Easy Glossary
Contributors: GrayStudio LLC, Yurii Duchenko, graystudio
Tags: glossary, tooltips, terms, index, dictionary
Requires at least: 5.7
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, flexible glossary plugin that auto-links terms, shows tooltips, and provides an index shortcode.

== Description ==

Easy Glossary plugin helps you create a glossary of terms and definitions, automatically link terms in your content, display optional tooltips, and render an A–Z index. It includes a Settings page to customize behavior.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Glossary → Settings to configure options.

== Frequently Asked Questions ==

= Can I disable auto-linking? =
Yes. Toggle it off in Glossary → Settings.

= Does it work with any theme? =
Yes, it uses standard hooks and minimal CSS.

= How can I show glossary archive? =
To show the glossary archive, you need to use the shortcode [gseasy_glossary].

== Changelog ==

= 1.1 =
* Added a per-term Schema (JSON-LD) textarea under glossary item editing.
* Added output of custom term schema in the page header on single glossary term pages.

= 1.0 =
* Initial public release.
