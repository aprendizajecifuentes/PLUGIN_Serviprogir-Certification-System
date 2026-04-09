<?php
/**
 * Lógica visual y de gestión del Panel Docente.
 * Renderiza el shortcode [scs_panel_docente]
 */

if (!defined('ABSPATH')) exit; // Seguridad: Evita el acceso directo al archivo

add_shortcode('scs_panel_docente', 'scs_render_panel_docente');

function scs_render_panel_docente() {
    // 1. Verificación de seguridad básica
    if (!is_user_logged_in()) {
        return "<div class='scs-main-panel'><p class='scs-error'>Acceso restringido. Debes iniciar sesión.</p></div>";
    }

    $user_id = get_current_user_id();
    $courses = get_user_meta($user_id, 'instructor_courses', true);
    $course_id_selected = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

    // 2. Procesar Acciones (Aprobar, Desaprobar, Enviar Certificados)
    if (isset($_POST['scs_aprobar']) || isset($_POST['scs_desaprobar']) || isset($_POST['scs_enviar_certificados'])) {
        if (isset($_POST['scs_nonce']) && wp_verify_nonce($_POST['scs_nonce'], 'scs_aprobar_estudiantes')) {
            $c_id = intval($_POST['course_id']);
            $student_ids = isset($_POST['students']) ? array_map('intval', $_POST['students']) : [];
            
            // Lógica de Aprobar/Desaprobar
            if (!empty($student_ids) && (isset($_POST['scs_aprobar']) || isset($_POST['scs_desaprobar']))) {
                foreach ($student_ids as $s_id) {
                    if (isset($_POST['scs_aprobar'])) {
                        update_user_meta($s_id, 'scs_aprobado_' . $c_id, date('d-m-Y H:i:s'));
                    } else {
                        delete_user_meta($s_id, 'scs_aprobado_' . $c_id);
                    }
                }
                echo "<div class='scs-notice' style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>✅ Cambios de estado actualizados con éxito.</div>";
            }

            // NUEVA LÓGICA: Enviar Certificados
            if (isset($_POST['scs_enviar_certificados'])) {
                // Doble validación de seguridad (por si un docente manipula el HTML)
                if (!current_user_can('edit_others_posts')) {
                    echo "<div class='scs-error' style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>⛔ Error: No tienes permisos para enviar certificados.</div>";
                } else {
                    if (!empty($student_ids)) {
                        $count = count($student_ids);
                        
                        /// NUEVA LÓGICA: Enviar Certificados
            if (isset($_POST['scs_enviar_certificados'])) {
                // Doble validación de seguridad
                if (!current_user_can('edit_others_posts')) {
                    echo "<div class='scs-error' style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>⛔ Error: No tienes permisos para enviar certificados.</div>";
                } else {
                    if (!empty($student_ids)) {
                        $all_approved = true;
                        $unapproved_names = []; // Guardaremos los nombres de los que fallen

                        // FASE 1: VALIDACIÓN ESTRICTA
                        foreach ($student_ids as $s_id) {
                            $is_approved = get_user_meta($s_id, 'scs_aprobado_' . $c_id, true);
                            
                            // Si está vacío, significa que no está aprobado
                            if (empty($is_approved)) {
                                $all_approved = false;
                                $user_info = get_userdata($s_id);
                                $unapproved_names[] = $user_info->display_name; // Guardamos el nombre para el mensaje de error
                            }
                        }

                        // FASE 2: DECISIÓN Y ENVÍO
                        if (!$all_approved) {
                            // Si alguien no está aprobado, abortamos todo
                            $nombres_error = implode(', ', $unapproved_names);
                            echo "<div class='scs-error' style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>
                                    ⛔ <strong>Envío cancelado.</strong><br> 
                                    Los siguientes estudiantes seleccionados NO están aprobados: <strong>{$nombres_error}</strong>.<br> 
                                    <em>Debes seleccionar únicamente a estudiantes con estado 'Aprobado' para emitir certificados.</em>
                                  </div>";
                        } else {
                            // Si todos están aprobados, enviamos los correos
                            $enviados_count = 0;
                            $course_title = get_the_title($c_id);

                            foreach ($student_ids as $s_id) {
                                $user_info = get_userdata($s_id);
                                $to = $user_info->user_email;
                                $subject = "Certificado disponible: " . $course_title;
                                
                                // Cuerpo del correo (Formato HTML básico)
                                $message = "<h2>¡Felicidades {$user_info->display_name}!</h2>";
                                $message .= "<p>Te informamos que has aprobado con éxito el curso <strong>'{$course_title}'</strong>.</p>";
                                $message .= "<p>Tu certificado ya se encuentra disponible. Puedes ingresar a la plataforma en tu perfil de estudiante para descargarlo.</p>";
                                $message .= "<p>Atentamente,<br>El equipo de formación.</p>";
                                
                                // Cabeceras para permitir formato HTML en el correo
                                $headers = array('Content-Type: text/html; charset=UTF-8');

                                // wp_mail devuelve true si el servidor de correo aceptó el mensaje
                                if (wp_mail($to, $subject, $message, $headers)) {
                                    $enviados_count++;
                                }
                            }
                            
                            echo "<div class='scs-notice' style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>
                                    📧 <strong>¡Éxito!</strong> Se han enviado los avisos de certificación a {$enviados_count} alumno(s).
                                  </div>";
                        }
                    } else {
                        echo "<div class='scs-error' style='background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>⚠️ Por favor, selecciona al menos un alumno para enviar el certificado.</div>";
                    }
                }
            }
                        echo "<div class='scs-notice' style='background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>📧 (Simulación) Se han procesado los certificados para {$count} alumno(s) seleccionado(s).</div>";
                    } else {
                        echo "<div class='scs-error' style='background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>⚠️ Por favor, selecciona al menos un alumno para enviar el certificado.</div>";
                    }
                }
            }
        }
    }

    // 3. Inicio del renderizado visual
    ob_start();
    echo "<div class='scs-main-panel'>"; 

    // --- VISTA A: GESTIÓN DE ALUMNOS DEL CURSO SELECCIONADO ---
    if ($course_id_selected) {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        // Lógica de Paginación
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $users_query = new WP_User_Query([
            'meta_key'     => 'course_' . $course_id_selected . '_access_from',
            'meta_compare' => 'EXISTS',
            'number'       => $per_page,
            'offset'       => $offset,
            'count_total'  => true 
        ]);

        $users = $users_query->get_results();
        $total_users = $users_query->get_total();
        $total_pages = ceil($total_users / $per_page);

        echo "<div class='scs-panel-card'>";
        
        // Barra de navegación superior
        echo "<div class='scs-nav-bar'>
                <a href='" . strtok($_SERVER["REQUEST_URI"], '?') . "' class='scs-back-link'>← Volver a Cursos</a>
                <div class='scs-filters'>
                    <a href='?course_id={$course_id_selected}&status=all' class='scs-filter-btn " . ($status_filter=='all'?'active':'') . "'>Todos</a>
                    <a href='?course_id={$course_id_selected}&status=approved' class='scs-filter-btn " . ($status_filter=='approved'?'active':'') . "'>Aprobados</a>
                    <a href='?course_id={$course_id_selected}&status=pending' class='scs-filter-btn " . ($status_filter=='pending'?'active':'') . "'>Pendientes</a>
                </div>
              </div>";

        echo "<h2 class='scs-course-title'>" . esc_html(get_the_title($course_id_selected)) . "</h2>";

        // Comprobar si hay estudiantes en esta página
        if (empty($users)) {
            echo "<p>No hay estudiantes para mostrar en esta vista.</p>";
        } else {
            $table_rows = '';
            
            // Construir filas de la tabla
            foreach ($users as $student) {
                $approval_data = get_user_meta($student->ID, 'scs_aprobado_' . $course_id_selected, true);
                $is_approved = !empty($approval_data);
                
                if (($status_filter == 'approved' && !$is_approved) || ($status_filter == 'pending' && $is_approved)) continue;

                $percentage = 0;
                if (function_exists('learndash_course_progress')) {
                    $progress = learndash_course_progress(['user_id' => $student->ID, 'course_id' => $course_id_selected, 'array' => true]);
                    $percentage = isset($progress['percentage']) ? intval($progress['percentage']) : 0;
                }

                $status_class = $is_approved ? 'approved' : 'pending';
                $status_text  = $is_approved ? 'Aprobado' : 'Pendiente';

                $table_rows .= "<tr class='scs-student-row'>
                    <td><input type='checkbox' class='student-checkbox' name='students[]' value='{$student->ID}'></td>
                    <td>{$student->ID}</td>
                    <td class='scs-name-cell'><strong>" . esc_html($student->display_name) . "</strong><br><span style='color: #666; font-size: 0.9em;'>{$student->user_email}</span></td>
                    <td>
                        <div class='scs-progress-bar' style='background: #eee; width: 100%; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 5px;'>
                            <div class='scs-progress-fill' style='background: #0073aa; width:{$percentage}%; height: 100%;'></div>
                        </div>
                        <small>{$percentage}%</small>
                    </td>
                    <td>
                        <span class='scs-status-pill {$status_class}'>{$status_text}</span><br>
                        <small style='color:#999'>" . ($is_approved && $approval_data !== "1" ? $approval_data : "") . "</small>
                    </td>
                </tr>";
            }

            // Renderizar Formulario y Tabla
            echo "<form method='post'>";
            wp_nonce_field('scs_aprobar_estudiantes', 'scs_nonce');
            echo "<input type='hidden' name='course_id' value='{$course_id_selected}'>";

            // Barra de acciones masivas
            echo "<div class='scs-action-bar' style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;'>
                    <div class='scs-stats-group'>
                        <span class='scs-stat-badge' style='background: #e9ecef; padding: 5px 10px; border-radius: 15px; font-size: 0.9em;'>Total Alumnos: {$total_users}</span>
                        <span class='scs-selected-badge' style='background: #e9ecef; padding: 5px 10px; border-radius: 15px; font-size: 0.9em; margin-left: 10px;'>Seleccionados: <span id='selected-count'>0</span></span>
                    </div>
                    <div class='scs-bulk-actions' style='display: flex; gap: 10px;'>
                        <button type='button' id='select-all' class='scs-btn scs-btn-secondary'>Todos</button>
                        <button type='button' id='deselect-all' class='scs-btn scs-btn-secondary'>Limpiar</button>
                        <button type='submit' name='scs_export_excel' class='scs-btn' style='background: #4CAF50; color: #fff; border: none;'>📊 Excel Total</button>";

            // ========================================================
            // MAGIA DE PERMISOS: Solo Editores/Admins ven este botón
            // ========================================================
            if ( current_user_can( 'edit_others_posts' ) ) { 
                echo "<button type='submit' name='scs_enviar_certificados' class='scs-btn' style='background: #f39c12; color: #fff; border: none;'>📧 Enviar Certificados</button>";
            }

            echo "  </div>
                  </div>";

            // Tabla
            echo "<table class='scs-student-table' style='width: 100%; text-align: left; border-collapse: collapse;'>
                    <thead>
                        <tr style='border-bottom: 2px solid #ddd;'>
                            <th style='padding: 10px;'>Verificar</th>
                            <th style='padding: 10px;'>ID</th>
                            <th style='padding: 10px;'>Estudiante</th>
                            <th style='padding: 10px;'>Progreso</th>
                            <th style='padding: 10px;'>Estado y Fecha</th>
                        </tr>
                    </thead>
                    <tbody>{$table_rows}</tbody>
                  </table>";
            
            // Controles de Paginación
            if ($total_pages > 1) {
                echo "<div class='scs-pagination' style='margin: 20px 0; text-align: center; display: flex; justify-content: center; gap: 10px; align-items: center;'>";
                $base_url = add_query_arg(['course_id' => $course_id_selected, 'status' => $status_filter], strtok($_SERVER["REQUEST_URI"], '?'));
                if ($current_page > 1) {
                    echo "<a href='".add_query_arg('paged', $current_page - 1, $base_url)."' class='scs-btn scs-btn-secondary' style='text-decoration:none; padding: 5px 15px; border: 1px solid #ccc; border-radius: 4px; color: #333;'>« Anterior</a>";
                }
                echo "<span class='scs-page-info' style='font-weight: bold;'>Página {$current_page} de {$total_pages}</span>";
                if ($current_page < $total_pages) {
                    echo "<a href='".add_query_arg('paged', $current_page + 1, $base_url)."' class='scs-btn scs-btn-secondary' style='text-decoration:none; padding: 5px 15px; border: 1px solid #ccc; border-radius: 4px; color: #333;'>Siguiente »</a>";
                }
                echo "</div>";
            }

            // Botones de Guardado
            echo "<div class='scs-table-footer' style='margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; display: flex; gap: 10px;'>
                    <button type='submit' name='scs_aprobar' class='scs-btn scs-btn-primary' style='background: #0073aa; color: white; border: none;'>✔ Aprobar Seleccionados</button>
                    <button type='submit' name='scs_desaprobar' class='scs-btn scs-btn-danger-outline' style='background: transparent; color: #dc3545; border: 1px solid #dc3545;'>✖ Quitar Aprobación</button>
                  </div>";
            echo "</form>";
        }
        echo "</div>"; // Fin scs-panel-card

    // --- VISTA B: LISTA DE CURSOS ASIGNADOS ---
    } else {
        echo "<div class='scs-panel-header' style='margin-bottom: 20px;'><h2>Panel Docente</h2></div>";
        echo "<div class='scs-panel-card' style='background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>";
        echo "<h3 style='margin-top: 0;'>Cursos Asignados</h3>";
        
        if (!empty($courses)) {
            foreach ($courses as $c_id) {
                echo "<div class='scs-course-item' style='padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;'>
                        <span><strong>" . esc_html(get_the_title($c_id)) . "</strong></span>
                        <a href='?course_id={$c_id}' class='scs-btn scs-btn-primary' style='background: #0073aa; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px;'>Gestionar</a>
                      </div>";
            }
        } else {
            echo "<p>No tienes cursos asignados actualmente.</p>";
        }
        echo "</div>";
    }

    echo "</div>"; // Fin scs-main-panel
    
    return ob_get_clean(); 
}