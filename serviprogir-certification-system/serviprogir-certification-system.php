<?php
/**
 * Plugin Name: Serviprogir Certification System
 * Description: Panel docente + validación + certificados LearnDash con diseño minimalista.
 * Version: 1.5.0
 * Author: Juan Cifuentes
 */

// Evitar acceso directo
if (!defined('ABSPATH')) exit;

add_shortcode('scs_panel_docente', 'scs_render_panel_docente');

// ===============================
// CARGAR JS Y CSS
// ===============================
add_action('wp_enqueue_scripts', 'scs_enqueue_scripts');

function scs_enqueue_scripts() {
    if (!is_user_logged_in()) return;

    global $post;

    // Solo cargar si el shortcode está presente en la página actual
    if (isset($post->post_content) && has_shortcode($post->post_content, 'scs_panel_docente')) {
        
        // Encolar JavaScript reactivo
        wp_enqueue_script(
            'scs-panel-js',
            plugin_dir_url(__FILE__) . 'assets/js/scs-panel.js',
            [],
            '1.1',
            true
        );

        // Encolar Estilos Minimalistas
        wp_enqueue_style(
            'scs-panel-style',
            plugin_dir_url(__FILE__) . 'assets/css/style-panel.css',
            [],
            '1.1'
        );
    }
}

// ===============================
// RENDERIZADO DEL PANEL (SHORTCODE)
// ===============================
function scs_render_panel_docente() {
    if (!is_user_logged_in()) {
        return "<div class='scs-main-panel'><p class='scs-error'>Debes iniciar sesión para acceder al panel docente.</p></div>";
    }

    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    
    // Obtener cursos asignados al instructor (Meta de usuario)
    $courses = get_user_meta($user_id, 'instructor_courses', true);
    $course_id_selected = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

    // ===============================
    // PROCESAR ACCIONES (POST)
    // ===============================
    if (isset($_POST['scs_aprobar']) || isset($_POST['scs_desaprobar'])) {
        if (isset($_POST['scs_nonce']) && wp_verify_nonce($_POST['scs_nonce'], 'scs_aprobar_estudiantes')) {
            
            $course_id = intval($_POST['course_id']);
            $student_ids = isset($_POST['students']) ? array_map('intval', $_POST['students']) : [];
            
            if (!empty($student_ids)) {
                foreach ($student_ids as $s_id) {
                    if (isset($_POST['scs_aprobar'])) {
                        update_user_meta($s_id, 'scs_aprobado_' . $course_id, 1);
                    } else {
                        delete_user_meta($s_id, 'scs_aprobado_' . $course_id);
                    }
                }
                echo "<div class='scs-notice'>✅ Los cambios se han guardado correctamente.</div>";
            }
        }
    }

    ob_start();
    echo "<div class='scs-main-panel'>"; 

    if ($course_id_selected) {
        // --- VISTA DETALLADA DEL CURSO ---
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        // Obtener usuarios con acceso a este curso específico de LearnDash
        $users = get_users([
            'meta_key'     => 'course_' . $course_id_selected . '_access_from',
            'meta_compare' => 'EXISTS'
        ]);

        echo "<div class='scs-panel-card'>";
        echo "<div class='scs-nav-bar'>
                <a href='" . strtok($_SERVER["REQUEST_URI"], '?') . "' class='scs-back-link'>← Volver</a>
                <div class='scs-filters'>
                    <a href='?course_id={$course_id_selected}&status=all' class='scs-filter-btn " . ($status_filter=='all'?'active':'') . "'>Todos</a>
                    <a href='?course_id={$course_id_selected}&status=approved' class='scs-filter-btn " . ($status_filter=='approved'?'active':'') . "'>Aprobados</a>
                    <a href='?course_id={$course_id_selected}&status=pending' class='scs-filter-btn " . ($status_filter=='pending'?'active':'') . "'>Pendientes</a>
                </div>
              </div>";

        echo "<h2 class='scs-course-title'>" . esc_html(get_the_title($course_id_selected)) . "</h2>";

        if (empty($users)) {
            echo "<p>No se encontraron estudiantes inscritos en este curso.</p>";
        } else {
            $total = 0; $approved = 0; $table_rows = '';

            // PROCESAMIENTO EN UNA SOLA PASADA (OPTIMIZADO)
            foreach ($users as $student) {
                $total++;
                $is_approved = (bool) get_user_meta($student->ID, 'scs_aprobado_' . $course_id_selected, true);
                
                if ($is_approved) $approved++;

                // Lógica de filtrado en servidor
                if (($status_filter == 'approved' && !$is_approved) || ($status_filter == 'pending' && $is_approved)) continue;

                // Obtener progreso de LearnDash
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
                    <td class='scs-name-cell'><strong>" . esc_html($student->display_name) . "</strong><br><span>{$student->user_email}</span></td>
                    <td>
                        <div class='scs-progress-bar'><div class='scs-progress-fill' style='width:{$percentage}%'></div></div>
                        <small>{$percentage}% completado</small>
                    </td>
                    <td><span class='scs-status-pill {$status_class}'>{$status_text}</span></td>
                </tr>";
            }

            // BARRA DE ACCIONES Y ESTADÍSTICAS
            echo "<div class='scs-action-bar'>
                    <div class='scs-stats-group'>
                        <span class='scs-stat-badge'>Total: {$total}</span>
                        <span class='scs-stat-badge approved'>Aprobados: {$approved}</span>
                        <span class='scs-stat-badge pending'>Pendientes: " . ($total - $approved) . "</span>
                        <span class='scs-selected-badge'>Seleccionados: <span id='selected-count'>0</span></span>
                    </div>
                    <div class='scs-bulk-actions'>
                        <button type='button' id='select-all' class='scs-btn scs-btn-secondary'>Seleccionar Todos</button>
                        <button type='button' id='deselect-all' class='scs-btn scs-btn-secondary'>Limpiar</button>
                    </div>
                  </div>";

            echo "<form method='post'>";
            wp_nonce_field('scs_aprobar_estudiantes', 'scs_nonce');
            
            echo "<table class='scs-student-table'>
                    <thead><tr><th>Verificar</th><th>ID</th><th>Estudiante</th><th>Progreso</th><th>Estado</th></tr></thead>
                    <tbody>{$table_rows}</tbody>
                  </table>";
            
            echo "<input type='hidden' name='course_id' value='{$course_id_selected}'>";
            echo "<div class='scs-table-footer'>
                    <button type='submit' name='scs_aprobar' class='scs-btn scs-btn-primary'>✔ Aprobar Seleccionados</button>
                    <button type='submit' name='scs_desaprobar' class='scs-btn scs-btn-danger-outline'>✖ Desaprobar Seleccionados</button>
                  </div>";
            echo "</form>";
        }
        echo "</div>"; 
    } else {
        // --- VISTA DE LISTA DE CURSOS ---
        echo "<div class='scs-panel-header'>
                <h1>Panel Docente</h1>
                <p class='welcome-text'>Sesión iniciada como: <strong>{$user->display_name}</strong></p>
              </div>";
        
        echo "<div class='scs-panel-card'>
                <h3 style='margin-top:0;'>Mis Cursos Asignados</h3>";
        
        if (empty($courses)) {
            echo "<p>Actualmente no tienes cursos bajo tu supervisión.</p>";
        } else {
            foreach ($courses as $c_id) {
                echo "<div class='scs-course-item' style='padding:15px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;'>
                        <span><strong>" . esc_html(get_the_title($c_id)) . "</strong></span>
                        <a href='?course_id={$c_id}' class='scs-btn scs-btn-primary'>Gestionar Estudiantes</a>
                      </div>";
            }
        }
        echo "</div>";
    }

    echo "</div>"; 
    return ob_get_clean();
}