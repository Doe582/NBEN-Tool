=== NBEN Cost Estimation Tool ===
Contributors: nben
Tags: nature-based solutions, cost estimation, climate adaptation, form builder
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0+

A dynamic multi-step cost estimation tool for Nature-based Solutions (NbS) with a conditional logic form builder and bilingual EN/FR support.

== Description ==

This plugin provides:

**Admin Form Builder**
- Gravity-Forms-style question editor
- Field types: radio, checkbox, select, text, number, textarea, info block
- Conditional logic: show/hide questions based on previous answers
- Bilingual (EN/FR) labels and help text
- Popup/tooltip content per question or choice
- Drag-and-drop question reordering

**Project Database**
- Custom Post Type: nben_project
- Taxonomies: NbS Type, Hazard, Infrastructure Type (NbS vs Grey)
- Cost & size metadata per project
- CSV bulk import
- Auto-calculate cost-per-unit from total cost ÷ total size

**Frontend Multi-step Form**
- Renders via shortcode: [nben_tool]
- Conditional question filtering based on answers
- Project selection from database matching user's NbS type
- "Limit to 3" option (low/mid/high cost representatives)
- Cost estimation: user project size × reference cost/unit
- Popup modals for contextual help
- Progress bar
- Responsive design
- WPML compatible

== Installation ==

1. Upload the `nben-tool` folder to `/wp-content/plugins/`
2. Activate the plugin via WordPress admin
3. Navigate to **NBEN Tool → Form Builder** to create your question flow
4. Navigate to **NBEN Tool → NbS Projects** to add reference projects
5. Set the active form in **NBEN Tool → Settings**
6. Add `[nben_tool]` shortcode to any page

== Shortcode ==

`[nben_tool]`                      – uses active form from Settings
`[nben_tool form_id="2"]`          – specific form
`[nben_tool lang="fr"]`            – force French language

== CSV Import Columns ==

title, description_en, description_fr, location, province, year,
total_size, size_unit (ha|m|m2|unit), cost_total, cost_per_unit,
currency_year, source_name, source_url,
nbs_type (pipe-separated), hazard (pipe-separated), infra_type (NbS|Grey Infrastructure)

== Changelog ==

= 1.0.0 =
* Initial release
