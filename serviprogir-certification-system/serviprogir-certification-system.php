<?php
/**
 * Plugin Name: Serviprogir Certification System
 * Description: Panel docente + validación + certificados LearnDash con trazabilidad de fechas.
 * Version: 1.7.5
 * Author: Juan Cifuentes
 */

if (!defined('ABSPATH')) exit; // Seguridad básica

// 1. Cargar dependencias (Nuestros módulos)
require_once plugin_dir_path(__FILE__) . 'includes/scs-excel-export.php';
require_once plugin_dir_path(__FILE__) . 'includes/scs-panel-docente.php';
require_once plugin_dir_path(__FILE__) . 'includes/scs-shortcode-alumno.php'; // <-- NUEVA LÍNEA

// 2. Cargar CSS y JS globales del plugin
add_action('wp_enqueue_scripts', 'scs_enqueue_scripts');
function scs_enqueue_scripts() {
    if (!is_user_logged_in()) return;
    global $post;
    if (isset($post->post_content) && has_shortcode($post->post_content, 'scs_panel_docente')) {
        wp_enqueue_script('scs-panel-js', plugin_dir_url(__FILE__) . 'assets/js/scs-panel.js', [], '1.3', true);
        wp_enqueue_style('scs-panel-style', plugin_dir_url(__FILE__) . 'assets/css/style-panel.css', [], '1.3');
    }
}

// =======================================================================
// SEGURIDAD: Eliminar el botón nativo de certificados de LearnDash
// =======================================================================
add_filter( 'learndash_certificate_button_html', 'scs_eliminar_boton_nativo_learndash', 10, 3 );

function scs_eliminar_boton_nativo_learndash( $button_html, $certificate_link, $certificate_post ) {
    // Retornar un texto vacío destruye el botón nativo en toda la plataforma.
    // Esto NO afecta a nuestro shortcode [scs_mi_certificado], ya que nosotros 
    // construimos nuestro propio botón HTML usando solo el enlace de descarga.
    return '';
}