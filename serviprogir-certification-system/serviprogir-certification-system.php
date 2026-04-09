<?php
/**
 * Plugin Name: Serviprogir Certification System
 * Description: Sistema integral para instructores: Gestión de certificados, firmas digitales, trazabilidad SST y reportes CSV.
 * Version: 2.2.0
 * Author: Juan Cifuentes / Servipro S.A.S
 */

if (!defined('ABSPATH')) exit;

// Definición de constantes para rutas
define('SCS_PATH', plugin_dir_path(__FILE__));
define('SCS_URL', plugin_dir_url(__FILE__));

// ===============================
// 1. MANEJO DE EXPORTACIÓN CSV
// ===============================
add_action('init', 'scs_handle_csv_export');
function scs_handle_csv_export() {
    if (isset($_POST['scs_export_csv']) && is_user_logged_in() && current_user_can('edit_posts')) {
        $course_id = intval($_POST['course_id']);
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        $users = get_users([
            'meta_key'     => 'course_' . $course_id . '_access_from',
            'meta_compare' => 'EXISTS'
        ]);

        if (empty($users)) return;

        $filename = "Reporte_SST_" . sanitize_title(get_the_title($course_id)) . "_" . date('d-m-Y') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para compatibilidad con Excel

        // Encabezados del reporte
        fputcsv($output, ['ID', 'Estudiante', 'Email', 'Progreso', 'Estado', 'Fecha Inscripción', 'Fecha Aprobación']);

        foreach ($users as $student) {
            $approval_data = get_user_meta($student->ID, 'scs_aprobado_' . $course_id, true);
            $enroll_ts = get_user_meta($student->ID, 'course_' . $course_id . '_access_from', true);
            $is_approved = !empty($approval_data);
            
            // Filtro dinámico según la vista actual
            if ($status_filter == 'approved' && !$is_approved) continue;
            if ($status_filter == 'pending' && $is_approved) continue;

            $percentage = 0;
            if (function_exists('learndash_course_progress')) {
                $progress = learndash_course_progress(['user_id' => $student->ID, 'course_id' => $course_id, 'array' => true]);
                $percentage = isset($progress['percentage']) ? $progress['percentage'] : 0;
            }

            fputcsv($output, [
                $student->ID,
                $student->display_name,
                $student->user_email,
                $percentage . '%',
                $is_approved ? 'Aprobado' : 'Pendiente',
                $enroll_ts ? date('d-m-Y', $enroll_ts) : 'N/A',
                ($is_approved && $approval_data !== "1") ? $approval_data : ($is_approved ? 'Aprobado' : 'Pendiente')
            ]);
        }
        fclose($output);
        exit;
    }
}

// ===============================
// 2. GESTIÓN DE FIRMA DEL DOCENTE
// ===============================
add_action('init', 'scs_handle_signature_upload');
function scs_handle_signature_upload() {
    if (isset($_POST['scs_upload_signature']) && wp_verify_nonce($_POST['scs_signature_nonce'], 'scs_upload_sig')) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $uploadedfile = $_FILES['signature_file'];
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            update_user_meta(get_current_user_id(), 'scs_docente_firma', $movefile['url']);
            wp_redirect(add_query_arg(['view' => 'profile', 'msg' => 'success'], strtok($_SERVER["REQUEST_URI"], '?')));
            exit;
        }
    }
}

// Shortcode para mostrar la firma en el PDF del certificado de LearnDash
add_shortcode('scs_firma_docente', 'scs_display_signature_shortcode');
function scs_display_signature_shortcode() {
    global $post;
    // Identificamos el curso relacionado al certificado
    $course_id = learndash_get_course_id($post->ID);
    if (!$course_id) return '_______________________';

    // Obtenemos el autor (instructor) del curso
    $instructor_id = get_post_field('post_author', $course_id);
    $firma_url = get_user_meta($instructor_id, 'scs_docente_firma', true);

    if ($firma_url) {
        return '<img src="'.esc_url($firma_url).'" style="max-width:180px; height:auto; display:block; margin:0 auto;">';
    }
    return '_______________________';
}

// ===============================
// 3. ENVÍO DE CORREO ELECTRÓNICO
// ===============================
function scs_enviar_certificado_email($user_id, $course_id) {
    $user = get_userdata($user_id);
    $course_title = get_the_title($course_id);
    $cert_link = function_exists('learndash_get_course_certificate_link') ? learndash_get_course_certificate_link($course_id, $user_id) : '';

    if (!$cert_link) return;

    $to = $user->user_email;
    $subject = "¡Felicitaciones! Estás certificado por Servipro S.A.S";
    
    $message = "
    <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; border: 1px solid #ddd; padding: 20px;'>
        <h2 style='color: #00bcd4;'>¡Hola, {$user->display_name}!</h2>
        <p>Has completado exitosamente el curso <strong>{$course_title}</strong>.</p>
        <p>Tu certificación ha sido aprobada y ya puedes descargar tu documento oficial desde el siguiente enlace:</p>
        <p style='text-align: center; margin: 30px 0;'>
            <a href='{$cert_link}' style='background: #22c55e; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>DESCARGAR CERTIFICADO</a>
        </p>
        <p style='font-size: 13px; color: #666;'>Este certificado cuenta con validación digital por código QR.</p>
        <hr style='border: none; border-top: 1px solid #eee;'>
        <p style='font-size: 11px; color: #999;'>Servipro S.A.S - Líderes en Capacitación SST</p>
    </div>";

    $headers = ['Content-Type: text/html; charset=UTF-8', 'From: Servipro S.A.S <soporte@servipro.com.co>'];
    wp_mail($to, $subject, $message, $headers);
}

// ===============================
// 4. RENDERIZADO DEL PANEL PRINCIPAL
// ===============================
add_shortcode('scs_panel_docente', 'scs_render_panel_docente');
function scs_render_panel_docente() {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        return "<div class='scs-main-panel'><p class='scs-error'>Acceso restringido a instructores.</p></div>";
    }

    $user_id = get_current_user_id();
    $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'courses';
    $course_id_selected = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $user_firma = get_user_meta($user_id, 'scs_docente_firma', true);

    // Lógica de Procesamiento Masivo
    if ((isset($_POST['scs_aprobar']) || isset($_POST['scs_desaprobar'])) && wp_verify_nonce($_POST['scs_nonce'], 'scs_bulk_action')) {
        if (isset($_POST['scs_aprobar']) && !$user_firma) {
            echo "<div class='scs-notice' style='border-left: 5px solid #ef4444;'>❌ Error: Debe subir su firma en la sección de perfil antes de aprobar.</div>";
        } else {
            $student_ids = isset($_POST['students']) ? array_map('intval', $_POST['students']) : [];
            $course_id = intval($_POST['course_id']);
            
            foreach ($student_ids as $s_id) {
                if (isset($_POST['scs_aprobar'])) {
                    update_user_meta($s_id, 'scs_aprobado_' . $course_id, date('d-m-Y H:i:s'));
                    scs_enviar_certificado_email($s_id, $course_id);
                } else {
                    delete_user_meta($s_id, 'scs_aprobado_' . $course_id);
                }
            }
            echo "<div class='scs-notice'>✅ Operación completada. Se han procesado los registros.</div>";
        }
    }

    ob_start();
    echo "<div class='scs-main-panel'>";

    if ($view === 'profile') {
        scs_get_template('signature-settings.php', ['user_firma' => $user_firma]);
    } elseif ($course_id_selected) {
        $students = get_users(['meta_key' => 'course_'.$course_id_selected.'_access_from', 'meta_compare' => 'EXISTS']);
        scs_get_template('student-management.php', [
            'course_id'     => $course_id_selected,
            'students'      => $students,
            'filter'        => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all',
            'has_signature' => !empty($user_firma)
        ]);
    } else {
        scs_get_template('course-list.php', [
            'courses'       => get_user_meta($user_id, 'instructor_courses', true),
            'user'          => wp_get_current_user(),
            'has_signature' => !empty($user_firma)
        ]);
    }

    echo "</div>";
    return ob_get_clean();
}

// ===============================
// 5. UTILIDADES Y ASSETS
// ===============================
function scs_get_template($template_name, $args = []) {
    $path = SCS_PATH . 'templates/' . $template_name;
    if (file_exists($path)) {
        extract($args);
        include $path;
    }
}

add_action('wp_enqueue_scripts', function() {
    global $post;
    if (isset($post->post_content) && has_shortcode($post->post_content, 'scs_panel_docente')) {
        wp_enqueue_style('scs-style', SCS_URL . 'assets/css/style-panel.css', [], '2.2.0');
        wp_enqueue_script('scs-script', SCS_URL . 'assets/js/scs-panel.js', [], '2.2.0', true);
    }
});