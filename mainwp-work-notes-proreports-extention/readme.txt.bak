=== MainWP Client Notes Pro Report Extension ===
Contributors: reallyusefulplugins
Donate link: https://reallyusefulplugins.com/donate
Tags: MainWP, ClientNotes, Pro-report
Requires at least: 6.5
Tested up to: 6.8.2
Stable tag: 1.3.0-beta.1
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Install on your dashboard within client sites you will now have a new area to add work notes and you can use them in your reports too.
== Description ==

Install on your dashboard within client sites you will now have a new area to add work notes and you can use them in your reports too.
== Installation ==

1. Upload the `mainwp-work-notes-proreports-extention` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure the plugin settings it is located in the Simply Static Menu

== Frequently Asked Questions ==

== Changelog ==
1.3.0
New:
-Migration system to move existing Work Notes from wp_options to a dedicated database table (wp_mainwp_work_notes).
-Admin toolbar button to trigger migration (only visible to admins if migration hasn’t yet run).
-Support for Flatpickr-powered date selection with WordPress-compatible display format.
-Automatically populate today’s date when creating a new note.
-Version-based logic to clean up legacy data and remove migration code in future versions.
-Pro Reports integration now supports multiple token formats:
-- [client.customwork.notes] (legacy table)
-- [client.customwork.notes_table] (CSS-stylable)
-- [client.customwork.notes_email] (email-safe, with mode support: default, compact, bordered).
- Email mode system for Pro Reports allows themeable layouts and site/report-based overrides.

Changed:
-Work Notes are now saved, retrieved, and displayed from a dedicated database table for performance and scalability.
-JS form handling improved:
-Notes load correctly into the editor and date field when editing.
-Form resets after save with today’s date pre-filled.
-Save button label dynamically changes between “Save Note” and “Update Note” based on context.
-Manual date entry is gated — only valid YYYY-MM-DD formats from the picker will save.
-Database queries for loading and deleting notes are now fully parameterized for security and stability.
-Migration JS injection improved for more reliable toolbar button behavior.

Clean-up:
-Migration logic grouped and documented for future removal in v1.3.2.
-Legacy wp_options notes will be removed automatically in v1.3.4+.
-Date picker logic centralized, fallback-safe, and synced with alt inputs.
-Enhanced UI interactivity with better error handling and user feedback.
-Minor code cleanups for consistency (no functional change).

1.2.5
New: Added Support for Preleases
New: Updated UUPD to 1.3.0


1.2.4 
- Added support for editing Work Notes using the WordPress visual editor (TinyMCE), enabling rich text formatting.
- Notes are now stored and displayed with HTML formatting, preserving line breaks, styling, and structure.
- Improved note editing logic to ensure existing notes (including index 0) are correctly updated instead of creating duplicates in HTML editor
- Update Plugin Description
1.23 - Update: UUPD - 1.25 & Improved: New Line Removal and Support for </br> - S Najman
1.22 - Scoped UUPD to make it unique to my plugins
1.21 - added language support on the notes display stable
added: dutch translation
added: support for client report extension < 4.0.15, needs the manual adding the token in client report admin
1.11 - V1.2 - Added automatic updates using GitHub to the plugin (Experimental)
Over the next week or so this will slowly increase to V1.2 as I test a new deploy script and updater for GitHub repos.
