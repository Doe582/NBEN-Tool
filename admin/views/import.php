<?php
defined( 'ABSPATH' ) || exit;

// Handle CSV import
if ( isset( $_POST['nben_import_submit'] ) && check_admin_referer( 'nben_import' ) ) {
    if ( ! empty( $_FILES['nben_csv']['tmp_name'] ) ) {
        $results = nben_import_csv( $_FILES['nben_csv']['tmp_name'] );
        echo '<div class="notice notice-success"><p>Imported ' . (int) $results['imported'] . ' projects. Skipped: ' . (int) $results['skipped'] . '.</p></div>';
    }
}

function nben_import_csv( string $file ): array {
    $imported = 0;
    $skipped  = 0;
    $handle   = fopen( $file, 'r' );
    if ( ! $handle ) return [ 'imported' => 0, 'skipped' => 0 ];

    $headers = fgetcsv( $handle );
    if ( ! $headers ) { fclose( $handle ); return [ 'imported' => 0, 'skipped' => 0 ]; }

    $headers = array_map( 'trim', $headers );

    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $data = array_combine( $headers, $row );
        if ( ! $data || empty( $data['title'] ) ) { $skipped++; continue; }

        $post_id = wp_insert_post( [
            'post_title'   => sanitize_text_field( $data['title'] ),
            'post_content' => sanitize_textarea_field( $data['description_en'] ?? '' ),
            'post_status'  => 'publish',
            'post_type'    => 'nben_project',
        ] );

        if ( is_wp_error( $post_id ) ) { $skipped++; continue; }

        $meta_map = [
            'location'        => '_nben_location',
            'province'        => '_nben_province',
            'description_fr'  => '_nben_description_fr',
            'total_size'      => '_nben_total_size',
            'size_unit'       => '_nben_size_unit',
            'cost_total'      => '_nben_cost_total',
            'cost_per_unit'   => '_nben_cost_per_unit',
            'currency_year'   => '_nben_currency_year',
            'source_name'     => '_nben_source_name',
            'source_url'      => '_nben_source_url',
            'year'            => '_nben_year',
        ];
        foreach ( $meta_map as $col => $meta_key ) {
            if ( isset( $data[ $col ] ) && $data[ $col ] !== '' ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $data[ $col ] ) );
            }
        }

        // Auto-calc cost per unit
        $ts = (float) ( $data['total_size'] ?? 0 );
        $tc = (float) ( $data['cost_total'] ?? 0 );
        if ( $ts > 0 && $tc > 0 && empty( $data['cost_per_unit'] ) ) {
            update_post_meta( $post_id, '_nben_cost_per_unit', round( $tc / $ts, 2 ) );
        }

        // Taxonomies
        if ( ! empty( $data['nbs_type'] ) ) {
            wp_set_object_terms( $post_id, explode( '|', $data['nbs_type'] ), 'nben_project_type' );
        }
        if ( ! empty( $data['hazard'] ) ) {
            wp_set_object_terms( $post_id, explode( '|', $data['hazard'] ), 'nben_hazard' );
        }
        if ( ! empty( $data['infra_type'] ) ) {
            wp_set_object_terms( $post_id, [ $data['infra_type'] ], 'nben_infra_type' );
        } else {
            wp_set_object_terms( $post_id, [ 'NbS' ], 'nben_infra_type' );
        }

        $imported++;
    }
    fclose( $handle );
    return [ 'imported' => $imported, 'skipped' => $skipped ];
}
?>
<div class="wrap">
<h1><?php esc_html_e( 'Import Projects', 'nben-tool' ); ?></h1>

<div style="max-width:700px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:24px;margin-top:16px">
    <h2><?php esc_html_e( 'CSV Import', 'nben-tool' ); ?></h2>
    <p><?php esc_html_e( 'Upload a CSV file with the following columns (first row = headers):', 'nben-tool' ); ?></p>

    <code style="display:block;background:#f6f7f7;padding:10px;border-radius:4px;font-size:12px;margin-bottom:16px">
        title, description_en, description_fr, location, province, year,<br>
        total_size, size_unit, cost_total, cost_per_unit, currency_year,<br>
        source_name, source_url, nbs_type, hazard, infra_type
    </code>

    <p class="description">
        <?php esc_html_e( 'For multiple values in nbs_type or hazard, separate with | (pipe). e.g. Flooding|Erosion', 'nben-tool' ); ?><br>
        <?php esc_html_e( 'infra_type: "NbS" or "Grey Infrastructure"', 'nben-tool' ); ?><br>
        <?php esc_html_e( 'size_unit: ha | m | m2 | unit', 'nben-tool' ); ?>
    </p>

    <form method="post" enctype="multipart/form-data" style="margin-top:20px">
        <?php wp_nonce_field( 'nben_import' ); ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'CSV File', 'nben-tool' ); ?></th>
                <td><input type="file" name="nben_csv" accept=".csv" required></td>
            </tr>
        </table>
        <p><input type="submit" name="nben_import_submit" class="button button-primary" value="<?php esc_attr_e( 'Import', 'nben-tool' ); ?>"></p>
    </form>

    <hr>
    <h2><?php esc_html_e( 'Download Template', 'nben-tool' ); ?></h2>
    <a href="<?php echo esc_url( NBEN_PLUGIN_URL . 'assets/sample-import.csv' ); ?>" class="button">
        <?php esc_html_e( 'Download Sample CSV', 'nben-tool' ); ?>
    </a>
</div>
</div>
