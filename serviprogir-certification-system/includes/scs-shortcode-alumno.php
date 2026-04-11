<?php
/**
 * Lógica visual para el estudiante.
 * Renderiza el shortcode [scs_mi_certificado]
 */

if (!defined('ABSPATH')) exit; // Seguridad

add_shortcode('scs_mi_certificado', 'scs_render_certificado_alumno');

function scs_render_certificado_alumno($atts) {
    // 1. Validar que sea un usuario logueado
    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();

    // 2. Detectar el ID del curso automáticamente
    $atts = shortcode_atts([
        'course_id' => 0,
    ], $atts);

    $course_id = intval($atts['course_id']);
    
    // Si no se pasó un ID, intentamos adivinarlo con LearnDash
    if ($course_id === 0 && function_exists('learndash_get_course_id')) {
        $course_id = learndash_get_course_id();
    }

    if (empty($course_id)) {
        return '';
    }

    // 3. Verificar progreso en LearnDash
    $percentage = 0;
    if (function_exists('learndash_course_progress')) {
        $progress = learndash_course_progress(['user_id' => $user_id, 'course_id' => $course_id, 'array' => true]);
        $percentage = isset($progress['percentage']) ? intval($progress['percentage']) : 0;
    }

    ob_start();

    // 4. LÓGICA VISUAL SEGÚN ESTADO
    if ($percentage < 100) {
        // A: No ha terminado el 100%
        echo "<div style='background: #f8f9fa; border-left: 4px solid #adb5bd; padding: 15px; margin-top: 20px; border-radius: 4px; color: #495057;'>
                <p style='margin: 0;'><em>Completa el curso al 100% para iniciar el proceso de certificación. (Progreso actual: {$percentage}%)</em></p>
              </div>";
    } else {
        // B: Terminó el 100%. Verificamos si el admin ya validó la práctica.
        $cert_enviado = get_user_meta($user_id, 'scs_certificado_enviado_' . $course_id, true);

        if (empty($cert_enviado)) {
            // B.1: Terminado, pero RETENIDO por el docente/admin
            echo "<div style='background: #fff3cd; border-left: 5px solid #ffeeba; padding: 15px; margin-top: 20px; border-radius: 4px; color: #856404;'>
                    <h4 style='margin-top: 0; color: #856404;'>🎓 Curso Online Completado</h4>
                    <p style='margin-bottom: 0;'>Tu certificado está <strong>retenido</strong> en proceso de validación. Se habilitará en esta sección una vez que el docente confirme tu práctica presencial y el área administrativa valide tu estado.</p>
                  </div>";
        } else {
            // B.2: LIBERADO (El admin validó y el correo fue enviado)
            $cert_url = '';
            if (function_exists('learndash_get_course_certificate_link')) {
                $cert_url = learndash_get_course_certificate_link($course_id, $user_id);
            }

            if (!empty($cert_url)) {
                echo "<div style='background: #d4edda; border-left: 5px solid #c3e6cb; padding: 20px; margin-top: 20px; border-radius: 4px; color: #155724; text-align: center;'>
                        <h3 style='margin-top: 0; color: #155724;'>🎉 ¡Felicidades!</h3>
                        <p>Tu práctica presencial y estado administrativo han sido validados con éxito.</p>
                        <a href='{$cert_url}' target='_blank' style='background-color: #0073aa; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block; margin-top: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>📥 Descargar Mi Certificado Oficial</a>
                      </div>";
            } else {
                echo "<p style='color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;'>Error interno: El certificado está liberado, pero no hay plantilla asignada al curso.</p>";
            }
        }
    }

    return ob_get_clean();
}