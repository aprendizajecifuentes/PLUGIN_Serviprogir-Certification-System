<?php
/**
 * Plugin Name: Serviprogir Certification System
 * Version: 1.8.0
 * Author: Juan Cifuentes
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/scs-excel-export.php';
require_once plugin_dir_path(__FILE__) . 'includes/scs-panel-docente.php';
require_once plugin_dir_path(__FILE__) . 'includes/scs-shortcode-alumno.php';

/**
 * FIX #1 — Ruta corregida. Antes: 'includes/fpdf/fpdf.php' (no existe)
 * Ahora: 'fpdf/fpdf.php' (ubicación real en el repositorio)
 */
function scs_load_fpdf() {
    if (!class_exists('FPDF')) {
        $fpdf_path = plugin_dir_path(__FILE__) . 'fpdf/fpdf.php';
        if (!file_exists($fpdf_path)) {
            error_log('[SCS] ERROR: No se encontró fpdf.php en: ' . $fpdf_path);
            return false;
        }
        require_once $fpdf_path;
    }
    return true;
}

/**
 * FIX #2 — utf8_decode() deprecado en PHP 8.2+.
 * iconv con //TRANSLIT maneja acentos y caracteres especiales en español.
 */
function scs_encode_text($text) {
    if (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    }
    return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
}

// ── Assets ────────────────────────────────────────────────────────────────────
add_action('wp_enqueue_scripts', 'scs_enqueue_scripts');
function scs_enqueue_scripts() {
    if (!is_user_logged_in()) return;
    global $post;
    if (isset($post->post_content) && has_shortcode($post->post_content, 'scs_panel_docente')) {
        wp_enqueue_script('scs-panel-js', plugin_dir_url(__FILE__) . 'assets/js/scs-panel.js', [], '1.4', true);
        // FIX #5 — Registrar ajaxurl para que el JS pueda usarlo correctamente
        wp_localize_script('scs-panel-js', 'scs_vars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
        ]);
        wp_enqueue_style('scs-panel-style', plugin_dir_url(__FILE__) . 'assets/css/style-panel.css', [], '1.3');
    }
}

// ── Hook automático al completar curso ────────────────────────────────────────
add_action('learndash_course_completed', 'scs_automatizar_envio_certificado', 10, 1);

function scs_automatizar_envio_certificado($data) {
    $user_id   = $data['user']->ID;
    $course_id = $data['course']->ID;

    $user_info         = get_userdata($user_id);
    $nombre_estudiante = $user_info->display_name;
    $correo_estudiante = $user_info->user_email;
    $nombre_curso      = get_the_title($course_id);

    if (!scs_load_fpdf()) {
        error_log('[SCS] Fallo al cargar FPDF para user_id=' . $user_id);
        return;
    }

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();

    $ruta_plantilla = plugin_dir_path(__FILE__) . 'assets/images/certificado-bg.jpg';
    if (file_exists($ruta_plantilla)) {
        $pdf->Image($ruta_plantilla, 0, 0, 297, 210);
    } else {
        error_log('[SCS] Advertencia: imagen de fondo no encontrada en: ' . $ruta_plantilla);
    }

    // FIX #2 aplicado: scs_encode_text() en lugar de utf8_decode()
    $pdf->SetFont('Helvetica', 'B', 24);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetXY(0, 100);
    $pdf->Cell(297, 10, scs_encode_text($nombre_estudiante), 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 16);
    $pdf->SetXY(0, 115);
    $pdf->Cell(297, 10, scs_encode_text('Por haber completado exitosamente el curso:'), 0, 1, 'C');

    $pdf->SetFont('Helvetica', 'I', 18);
    $pdf->SetXY(0, 130);
    $pdf->Cell(297, 10, scs_encode_text($nombre_curso), 0, 1, 'C');

    $upload_dir   = wp_upload_dir();
    $pdf_filename = 'Certificado_' . sanitize_file_name($nombre_estudiante) . '_' . time() . '.pdf';
    $pdf_path     = $upload_dir['path'] . '/' . $pdf_filename;

    // FIX #4 — Validar permisos de escritura antes de guardar
    if (!is_writable($upload_dir['path'])) {
        error_log('[SCS] ERROR: Directorio sin permisos de escritura: ' . $upload_dir['path']);
        return;
    }

    $pdf->Output('F', $pdf_path);

    if (!file_exists($pdf_path)) {
        error_log('[SCS] ERROR: El PDF no fue creado en: ' . $pdf_path);
        return;
    }

    $asunto   = '¡Felicidades! Aquí tienes tu certificado de ' . $nombre_curso;
    $mensaje  = "Hola $nombre_estudiante,\n\nTu práctica ha sido validada y aprobada en '$nombre_curso'.\n\nAdjunto encontrarás tu certificado oficial.\n\nSaludos,\nEl equipo de ServiProGIr.";
    $headers  = ['Content-Type: text/plain; charset=UTF-8'];

    $enviado = wp_mail($correo_estudiante, $asunto, $mensaje, $headers, [$pdf_path]);
    if (!$enviado) {
        error_log('[SCS] ERROR: wp_mail() falló para: ' . $correo_estudiante);
    }

    if (file_exists($pdf_path)) {
        unlink($pdf_path);
    }
}

// ── AJAX: Vista previa del PDF en el panel docente ────────────────────────────
add_action('wp_ajax_ver_certificado_pdf', 'scs_ver_certificado_pdf_callback');

function scs_ver_certificado_pdf_callback() {
    if (!current_user_can('edit_others_posts')) {
        wp_die('Sin permisos.', 'Acceso denegado', ['response' => 403]);
    }

    $user_id   = isset($_GET['user_id'])   ? intval($_GET['user_id'])   : 0;
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

    if (!$user_id || !$course_id) {
        wp_die('Se requieren user_id y course_id válidos.');
    }

    if (!scs_load_fpdf()) {
        wp_die('Error interno: no se pudo cargar la librería PDF.');
    }

    $user_info    = get_userdata($user_id);
    $nombre       = $user_info ? $user_info->display_name : 'Usuario desconocido';
    $nombre_curso = get_the_title($course_id) ?: 'Curso sin título';

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();

    $bg_path = plugin_dir_path(__FILE__) . 'assets/images/certificado-bg.jpg';
    if (file_exists($bg_path)) {
        $pdf->Image($bg_path, 0, 0, 297, 210);
    }

    $pdf->SetFont('Helvetica', 'B', 30);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetXY(0, 80);
    $pdf->Cell(297, 10, scs_encode_text($nombre), 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 18);
    $pdf->SetXY(0, 100);
    $pdf->Cell(297, 10, scs_encode_text('Por haber completado con éxito el curso:'), 0, 1, 'C');

    $pdf->SetFont('Helvetica', 'I', 22);
    $pdf->SetXY(0, 118);
    $pdf->Cell(297, 15, scs_encode_text($nombre_curso), 0, 1, 'C');

    // FIX #3 — Limpiar output buffer ANTES de enviar el PDF al navegador.
    // Si WordPress o algún plugin ya escribió HTML, el PDF llega corrupto.
    if (ob_get_length()) {
        ob_end_clean();
    }

    $pdf->Output('I', 'Certificado-' . $user_id . '-' . $course_id . '.pdf');
    exit;
}