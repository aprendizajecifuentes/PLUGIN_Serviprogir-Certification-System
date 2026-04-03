# PLUGIN_Serviprogir-Certification-System
Plugin Wordpress + Learndash -> shortcode genera un panel para docentes que permite ver progreso de alumnos y aprobarlos.
## Instrucciones:
En el administrador de wordpress (WP-admin)/Temas/Editor de archivos de Temas/functios PHP ,o en su defecto usando plgin Seleccionar Code Snippets Code Snippets, Debes agregar el siguiente codigo:
update_user_meta(ID_Instructor, 'instructor_courses', [ID_curso1, ID_curso2]);

ID_Instructor -> Identificador unico de Usuario que corresponde a el instructor.
ID_curso      -> Identificador unico de Curso que dicta el instructor.
## ShortCode
Crea una pagina nueva llamada "Panel Docente" o como quieras llamarla e ingresa el siguinete Shortcode
[scs_panel_docente]
## estructura proyecto
serviprogir-certification-system/
│
├── serviprogir-certification-system.php
└── assets/
└── js/
└── scs-panel.js

Prueba de carga 1
