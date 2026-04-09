<?php
if (!defined('ABSPATH')) exit; // Evitar acceso directo

add_action('init', 'scs_handle_excel_export');

function scs_handle_excel_export() {
    if (isset($_POST['scs_export_excel']) && is_user_logged_in()) {
        $course_id = intval($_POST['course_id']);
        $course_title = get_the_title($course_id);
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        // Verificamos si el usuario tiene privilegios avanzados
        $can_manage_certs = current_user_can('edit_others_posts');

        $users = get_users([
            'meta_key'     => 'course_' . $course_id . '_access_from',
            'meta_compare' => 'EXISTS'
        ]);

        if (empty($users)) return;

        $suffix = ($status_filter !== 'all') ? "_" . $status_filter : "";
        $filename = "Reporte_" . sanitize_title($course_title) . $suffix . "_" . date('d-m-Y') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

        // Construir encabezados dinámicamente
        $csv_headers = ['ID', 'Estudiante', 'Email', 'Progreso', 'Estado', 'Fecha de Aprobación'];
        if ($can_manage_certs) {
            $csv_headers[] = 'Fecha Envío Certificado'; // Columna extra solo para admin/editores
        }
        fputcsv($output, $csv_headers);

        foreach ($users as $student) {
            $approval_data = get_user_meta($student->ID, 'scs_aprobado_' . $course_id, true);
            $is_approved = !empty($approval_data);
            
            if ($status_filter == 'approved' && !$is_approved) continue;
            if ($status_filter == 'pending' && $is_approved) continue;

            $fecha_display = ($is_approved && $approval_data !== "1") ? $approval_data : ($is_approved ? 'Aprobado (Sin fecha)' : 'Pendiente');
            
            $percentage = 0;
            if (function_exists('learndash_course_progress')) {
                $progress = learndash_course_progress(['user_id' => $student->ID, 'course_id' => $course_id, 'array' => true]);
                $percentage = isset($progress['percentage']) ? $progress['percentage'] : 0;
            }

            // Construir la fila dinámica
            $csv_row = [
                $student->ID, 
                $student->display_name, 
                $student->user_email, 
                $percentage . '%', 
                $is_approved ? 'Aprobado' : 'Pendiente', 
                $fecha_display
            ];

            // Si es admin/editor, agregamos el dato extra a la fila
            if ($can_manage_certs) {
                $cert_enviado = get_user_meta($student->ID, 'scs_certificado_enviado_' . $course_id, true);
                $csv_row[] = !empty($cert_enviado) ? $cert_enviado : 'No enviado';
            }

            fputcsv($output, $csv_row);
        }
        fclose($output);
        exit;
    }
}