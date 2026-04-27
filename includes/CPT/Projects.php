<?php
namespace NBEN\CPT;

defined( 'ABSPATH' ) || exit;

/**
 * Registers:
 *  - CPT: nben_project
 *  - Taxonomy: nben_project_type  (NbS type e.g. Bioswale, Rain Garden…)
 *  - Taxonomy: nben_project_hazard (Flooding, Erosion, Habitat, Heat)
 *  - Taxonomy: nben_infra_type    (NbS | Grey)
 */
class Projects {

    public function register(): void {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
    }

    public function register_cpt(): void {
        $labels = [
            'name'               => __( 'Projects', 'nben-tool' ),
            'singular_name'      => __( 'Project', 'nben-tool' ),
            'add_new'            => __( 'Add Project', 'nben-tool' ),
            'add_new_item'       => __( 'Add New Project', 'nben-tool' ),
            'edit_item'          => __( 'Edit Project', 'nben-tool' ),
            'view_item'          => __( 'View Project', 'nben-tool' ),
            'search_items'       => __( 'Search Projects', 'nben-tool' ),
            'not_found'          => __( 'No projects found', 'nben-tool' ),
            'not_found_in_trash' => __( 'No projects in trash', 'nben-tool' ),
            'menu_name'          => __( 'NbS Projects', 'nben-tool' ),
        ];

        register_post_type( 'nben_project', [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'nben-tool',
            'show_in_rest'       => true,
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'menu_icon'          => 'dashicons-location-alt',
            'capability_type'    => 'post',
            'rewrite'            => false,
            'query_var'          => false,
        ] );
    }

    public function register_taxonomies(): void {

        // ── NbS / Project Type ──────────────────────────────────────────────
        register_taxonomy( 'nben_project_type', 'nben_project', [
            'labels'            => $this->tax_labels( __( 'NbS Types', 'nben-tool' ), __( 'NbS Type', 'nben-tool' ) ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );

        // ── Hazard ──────────────────────────────────────────────────────────
        register_taxonomy( 'nben_hazard', 'nben_project', [
            'labels'            => $this->tax_labels( __( 'Hazards', 'nben-tool' ), __( 'Hazard', 'nben-tool' ) ),
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );

        // ── Infrastructure type (NbS | Grey) ────────────────────────────────
        register_taxonomy( 'nben_infra_type', 'nben_project', [
            'labels'            => $this->tax_labels( __( 'Infrastructure Types', 'nben-tool' ), __( 'Infrastructure Type', 'nben-tool' ) ),
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );

        // Seed default terms on first load
        $this->maybe_seed_terms();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function tax_labels( string $plural, string $singular ): array {
        return [
            'name'              => $plural,
            'singular_name'     => $singular,
            'search_items'      => sprintf( __( 'Search %s', 'nben-tool' ), $plural ),
            'all_items'         => sprintf( __( 'All %s', 'nben-tool' ), $plural ),
            'edit_item'         => sprintf( __( 'Edit %s', 'nben-tool' ), $singular ),
            'add_new_item'      => sprintf( __( 'Add New %s', 'nben-tool' ), $singular ),
            'not_found'         => sprintf( __( 'No %s found', 'nben-tool' ), strtolower( $plural ) ),
            'menu_name'         => $plural,
        ];
    }

    private function maybe_seed_terms(): void {
        if ( get_option( 'nben_terms_seeded' ) ) return;

        // NbS Project Types
        $nbs_types = [
            'Bioswales', 'Rain Gardens', 'Bioretention Basins/Ponds',
            'Constructed Wetlands', 'Pollinator Gardens', 'Green Roofs',
            'Living Walls', 'Waterway Naturalization', 'Wetland Restoration',
            'Riparian Buffers', 'Permeable Pavement', 'Submerged Aquatic Vegetation',
            'Beach and Dune Restoration', 'Coastal Wetlands and Salt Marshes',
            'Oyster Reefs and Living Breakwaters', 'Other Shoreline Revegetation',
            'Tree Planting',
        ];
        foreach ( $nbs_types as $t ) {
            if ( ! term_exists( $t, 'nben_project_type' ) ) {
                wp_insert_term( $t, 'nben_project_type' );
            }
        }

        // Hazards
        foreach ( [ 'Flooding', 'Erosion', 'Habitat Loss', 'Heat Island Effect' ] as $h ) {
            if ( ! term_exists( $h, 'nben_hazard' ) ) {
                wp_insert_term( $h, 'nben_hazard' );
            }
        }

        // Infra types
        foreach ( [ 'NbS', 'Grey Infrastructure' ] as $i ) {
            if ( ! term_exists( $i, 'nben_infra_type' ) ) {
                wp_insert_term( $i, 'nben_infra_type' );
            }
        }

        update_option( 'nben_terms_seeded', 1 );
    }
}
