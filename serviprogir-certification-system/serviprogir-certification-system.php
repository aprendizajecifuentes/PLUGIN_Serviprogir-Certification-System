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

// Enganchar la función cuando un estudiante completa un curso
add_action('learndash_course_completed', 'scs_automatizar_envio_certificado', 10, 1);

function scs_automatizar_envio_certificado($data) {
    // 1. Extraer los datos vitales del disparo de LearnDash
    $user_id = $data['user']->ID;
    $course_id = $data['course']->ID;
    
    // 2. Obtener la información del estudiante y del curso
    $user_info = get_userdata($user_id);
    $nombre_estudiante = $user_info->display_name;
    $correo_estudiante = $user_info->user_email;
    $nombre_curso = get_the_title($course_id);
    
    // Opcional: Si solo quieres que esto pase en ciertos cursos, descomenta la línea de abajo y pon el ID de tu curso
    // if ($course_id != 1532) return; 

    // 3. Llamar a tu librería FPDF (Verifica que la ruta sea la correcta en tu plugin)
    if (!class_exists('FPDF')) {
        require_once plugin_dir_path(__FILE__) . 'fpdf/fpdf.php'; 
    }
    
    // 4. Crear el lienzo del PDF (Horizontal A4)
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    
    // 5. Estampar la imagen de fondo estática
    // Asegúrate de tener tu imagen 'certificado-bg.jpg' en esa ruta
    $ruta_plantilla = plugin_dir_path(__FILE__) . 'assets/images/certificado-bg.jpg';
    if (file_exists($ruta_plantilla)) {
        $pdf->Image($ruta_plantilla, 0, 0, 297, 210); 
    }
    
    // 6. Imprimir el Nombre del Estudiante en el PDF
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetXY(0, 100); // 100mm desde arriba, ajústalo según tu diseño
    // Centramos el texto a lo ancho de la hoja (297mm)
    $pdf->Cell(297, 10, utf8_decode($nombre_estudiante), 0, 1, 'C');
    
    // 7. Guardar temporalmente el PDF en el servidor
    $upload_dir = wp_upload_dir();
    $pdf_filename = 'Certificado_' . str_replace(' ', '_', $nombre_estudiante) . '.pdf';
    $pdf_path = $upload_dir['path'] . '/' . $pdf_filename;
    
    $pdf->Output('F', $pdf_path); // 'F' indica guardar en archivo (File)
    
    // 8. Preparar el Correo Electrónico
    $asunto = '¡Felicidades! Aquí tienes tu certificado de ' . $nombre_curso;
    $mensaje = "Hola $nombre_estudiante,\n\nTu práctica ha sido validada por tu instructor y has aprobado exitosamente el curso '$nombre_curso'.\n\nAdjunto a este correo encontrarás tu certificado oficial.\n\nSaludos cordiales,\nEl equipo de ServiProGIr.";
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    $adjuntos = array($pdf_path);
    
    // 9. Enviar el correo usando la función nativa de WordPress
    wp_mail($correo_estudiante, $asunto, $mensaje, $headers, $adjuntos);
    
    // 10. Borrar el archivo PDF para no saturar tu servidor
    if (file_exists($pdf_path)) {
        unlink($pdf_path);
    }
}
// 1. Registramos la acción para usuarios autenticados
add_action('wp_ajax_ver_certificado_pdf', 'scs_ver_certificado_pdf_callback');

function scs_ver_certificado_pdf_callback() {
    // Validar permisos y datos
    $user_id = intval($_GET['user_id']);
    $course_id = intval($_GET['course_id']);

    if (!$user_id || !$course_id) {
        wp_die('Datos insuficientes.');
    }

    // Requerir la librería FPDF (asegúrate que la ruta sea correcta)
    require_once(plugin_dir_path(__FILE__) . 'includes/fpdf/fpdf.php');

    // Configuración del PDF (Horizontal A4)
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    
    // Imagen de fondo (ajusta la ruta a tu imagen)
    $bg_path = plugin_dir_path(__FILE__) . 'assets/img/certificado-bg.jpg';
    if (file_exists($bg_path)) {
        $pdf->Image($bg_path, 0, 0, 297, 210);
    }

    // Datos del Estudiante
    $user_info = get_userdata($user_id);
    $nombre_completo = utf8_decode($user_info->display_name);
    $nombre_curso = utf8_decode(get_the_title($course_id));

    // Texto del Certificado
    $pdf->SetFont('Arial', 'B', 30);
    $pdf->SetXY(0, 80);
    $pdf->Cell(297, 10, $nombre_completo, 0, 1, 'C');

    $pdf->SetFont('Arial', '', 18);
    $pdf->SetXY(0, 100);
    $pdf->Cell(297, 10, "Por haber completado con exito el curso:", 0, 1, 'C');
    
    $pdf->SetFont('Arial', 'I', 22);
    $pdf->Cell(297, 15, $nombre_curso, 0, 1, 'C');

    // IMPORTANTE: El parámetro 'I' abre el PDF en el navegador
    $pdf->Output('I', 'Certificado-' . $user_id . '.pdf');
    exit;
}