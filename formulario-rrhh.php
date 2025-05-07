<?php
/**
 * Plugin Name: Formulario de Recursos Humanos
 * Description: Formulario para recibir CVs de postulantes.
 * Version: 1.0.0
 * Author: Luciano Sbarbati
 */

// Evita el acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

function formulario_rrhh_frontend() {
    ob_start();
    ?>
    <form method="post" enctype="multipart/form-data" id="formulario-postulacion">
        <div>
            <label for="nombre_apellido">Nombre y Apellido:</label>
            <input type="text" id="nombre_apellido" name="nombre_apellido" required>
        </div>

        <div>
            <label for="dni">DNI:</label>
            <input type="text" id="dni" name="dni" required>
        </div>

        <div>
            <label for="direccion">Dirección:</label>
            <input type="text" id="direccion" name="direccion" required>
        </div>

        <div>
            <label for="fecha_nacimiento">Fecha de nacimiento:</label>
            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>
        </div>

        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div>
            <label for="telefono">Teléfono de contacto:</label>
            <input type="tel" id="telefono" name="telefono" required>
        </div>

        <div>
            <label for="area_postulacion">ÁREA A LA QUE SE POSTULA:</label>
            <select id="area_postulacion" name="area_postulacion" required>
                <option value="">Seleccionar área</option>
                <option value="desarrollo">Desarrollo</option>
                <option value="diseno">Diseño</option>
                <option value="marketing">Marketing</option>
                </ul>
            </select>
        </div>

        <div>
            <label for="estudios">ESTUDIOS CURSADOS:</label>
            <select id="estudios" name="estudios" required>
                <option value="">Seleccionar estudios</option>
                <option value="primarios">Primarios</option>
                <option value="secundarios">Secundarios</option>
                <option value="terciarios">Terciarios</option>
                <option value="universitarios">Universitarios</option>
            </select>
        </div>

        <div>
            <label for="cv">CURRICULUM VITAE (máx. 2MB - Word o PDF):</label>
            <input type="file" id="cv" name="cv" accept=".doc,.docx,.pdf" required>
        </div>

        <button type="submit">Enviar Postulación</button>
    </form>
    <?php
    return ob_get_clean();
}

add_shortcode('formulario_rrhh', 'formulario_rrhh_frontend');

function procesar_formulario_rrhh() {
    if (isset($_POST['nombre_apellido'])) {
        // Recolección de datos
        $nombre_apellido = sanitize_text_field($_POST['nombre_apellido']);
        $dni = sanitize_text_field($_POST['dni']);
        $direccion = sanitize_text_field($_POST['direccion']);
        $fecha_nacimiento = sanitize_text_field($_POST['fecha_nacimiento']);
        $email = sanitize_email($_POST['email']);
        $telefono = sanitize_text_field($_POST['telefono']);
        $area_postulacion = sanitize_text_field($_POST['area_postulacion']);
        $estudios = sanitize_text_field($_POST['estudios']);

        // Subida del CV
        if ($_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $archivo_temporal = $_FILES['cv']['tmp_name'];
            $nombre_archivo = $_FILES['cv']['name'];
            $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
            $nombre_archivo_nuevo = sanitize_file_name(basename($nombre_archivo, '.' . $extension)) . '_' . time() . '.' . $extension;
            $directorio_subida = wp_upload_dir()['basedir'] . '/RRHH/';

            // Crea el directorio si no existe
            if (!is_dir($directorio_subida)) {
                wp_mkdir_p($directorio_subida);
            }

            $ruta_archivo = $directorio_subida . $nombre_archivo_nuevo;

            // Validar el tamaño del archivo (2MB = 2097152 bytes)
            if ($_FILES['cv']['size'] > 2097152) {
                echo '<div class="error">El archivo CV es demasiado grande. Por favor, suba un archivo de máximo 2MB.</div>';
                return;
            }

            // Validar la extensión del archivo
            $tipos_permitidos = array('doc', 'docx', 'pdf');
            if (!in_array(strtolower($extension), $tipos_permitidos)) {
                echo '<div class="error">Solo se permiten archivos Word (.doc, .docx) y PDF.</div>';
                return;
            }

            if (move_uploaded_file($archivo_temporal, $ruta_archivo)) {
                $url_cv = wp_upload_dir()['baseurl'] . '/RRHH/' . $nombre_archivo_nuevo;

                // Envío del email
                $to = get_option('admin_email'); // O la dirección de correo electrónico de RRHH
                $subject = 'Nueva postulación recibida';
                $body = "Se ha recibido una nueva postulación:\n\n" .
                        "Nombre y Apellido: " . $nombre_apellido . "\n" .
                        "DNI: " . $dni . "\n" .
                        "Dirección: " . $direccion . "\n" .
                        "Fecha de nacimiento: " . $fecha_nacimiento . "\n" .
                        "Email: " . $email . "\n" .
                        "Teléfono de contacto: " . $telefono . "\n" .
                        "Área a la que se postula: " . $area_postulacion . "\n" .
                        "Estudios cursados: " . $estudios . "\n" .
                        "CV del postulante: " . $url_cv;
                $headers = array('Content-Type: text/plain; charset=UTF-8');

                wp_mail($to, $subject, $body, $headers);

                // Guardar en Custom Post Type
                $post_data = array(
                    'post_title'    => $nombre_apellido,
                    'post_type'     => 'postulaciones_rrhh',
                    'post_status'   => 'pending', // O 'publish' si quieres aprobarlos automáticamente
                    'meta_input'    => array(
                        'dni'               => $dni,
                        'direccion'         => $direccion,
                        'fecha_nacimiento'  => $fecha_nacimiento,
                        'email'             => $email,
                        'telefono'          => $telefono,
                        'area_postulacion'  => $area_postulacion,
                        'estudios'          => $estudios,
                        'url_cv'            => $url_cv
                    ),
                );
                $post_id = wp_insert_post($post_data);

                if ($post_id) {
                    echo '<div class="success">Su postulación ha sido enviada correctamente.</div>';
                } else {
                    echo '<div class="error">Hubo un error al guardar su postulación. Por favor, inténtelo de nuevo.</div>';
                }

            } else {
                echo '<div class="error">Hubo un error al subir el archivo CV. Por favor, inténtelo de nuevo.</div>';
            }
        } else {
            echo '<div class="error">Por favor, suba su Curriculum Vitae.</div>';
        }
    }
}
add_action('init', 'procesar_formulario_rrhh');

function registrar_cpt_postulaciones_rrhh() {
    $labels = array(
        'name'               => 'Postulaciones RRHH',
        'singular_name'      => 'Postulación RRHH',
        'menu_name'          => 'Postulaciones RRHH',
        'name_admin_bar'     => 'Postulación RRHH',
//      'add_new'            => '', // Para que no se muestre el boton, se elimina con estilos más abajo.
        'add_new_item'       => '',
        'new_item'           => '',
        'edit_item'          => 'Editar Postulación',
        'view_item'          => 'Ver Postulación',
        'all_items'          => 'Todas las Postulaciones',
        'search_items'       => 'Buscar Postulaciones',
        'parent_item_colon'  => 'Postulaciones Padre:',
        'not_found'          => 'No se encontraron postulaciones.',
        'not_found_in_trash' => 'No se encontraron postulaciones en la papelera.',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'postulacion-rrhh'),
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'custom-fields'),
        'show_in_admin_bar'  => false, // Oculta en la barra de administración
        'can_export'         => true,
    );

    register_post_type('postulaciones_rrhh', $args);
}
add_action('init', 'registrar_cpt_postulaciones_rrhh');

function remover_boton_nuevo_postulacion() {
    global $typenow;
    if ($typenow === 'postulaciones_rrhh') {
        echo '<style type="text/css">
            .post-type-postulaciones_rrhh .page-title-action {
                display: none;
            }
        </style>';
    }
}
add_action('admin_head', 'remover_boton_nuevo_postulacion');

// Modificar las columnas de la lista de postulaciones
function set_custom_post_type_columns_postulaciones_rrhh($columns) {
    unset($columns['title']);
    unset($columns['date']);
    $new_columns = array(
        'nombre_apellido' => 'Nombre y Apellido',
        'dni'             => 'DNI',
        'area_postulacion' => 'Área',
        'cv'              => 'CV', // Agregamos la columna CV
        'fecha_carga'     => 'Fecha de Carga',
    );
    return array_merge($columns, $new_columns);
}
add_filter('manage_postulaciones_rrhh_posts_columns', 'set_custom_post_type_columns_postulaciones_rrhh');

// Mostrar los datos en las columnas personalizadas
function custom_post_type_columns_postulaciones_rrhh($column, $post_id) {
    switch ($column) {
        case 'nombre_apellido':
            echo get_the_title($post_id);
            break;
        case 'dni':
            echo esc_html(get_post_meta($post_id, 'dni', true));
            break;
        case 'area_postulacion':
            echo esc_html(get_post_meta($post_id, 'area_postulacion', true));
            break;
        case 'cv':
            $url_cv = esc_url(get_post_meta($post_id, 'url_cv', true));
            if ($url_cv) {
                echo '<a href="' . $url_cv . '" target="_blank">Ver CV</a>';
            } else {
                echo 'No se adjuntó CV';
            }
            break;
        case 'fecha_carga':
            $date_format = get_option('date_format') . ' ' . get_option('time_format');
            echo get_the_date($date_format, $post_id);
            break;
    }
}
add_action('manage_postulaciones_rrhh_posts_custom_column', 'custom_post_type_columns_postulaciones_rrhh', 10, 2);

// Hacer que la columna de fecha sea ordenable
function set_custom_post_type_sortable_columns_postulaciones_rrhh($sortable_columns) {
    $sortable_columns['fecha_carga'] = 'date';
    return $sortable_columns;
}
add_filter('manage_edit-postulaciones_rrhh_sortable_columns', 'set_custom_post_type_sortable_columns_postulaciones_rrhh');

// **Meta Boxes**
function rrhh_add_meta_boxes() {
    add_meta_box(
        'estado_notas_rrhh',
        'Estado y Notas',
        'rrhh_estado_notas_callback',
        'postulaciones_rrhh',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'rrhh_add_meta_boxes');

function rrhh_estado_notas_callback($post) {
    // Usar nonce para seguridad
    wp_nonce_field('rrhh_estado_notas_nonce', 'rrhh_estado_notas_nonce');

    // Campo de Aprobación/Rechazo
    $aprobado = get_post_meta($post->ID, '_aprobado', true);
    ?>
    <p>
        <label for="aprobado">Estado:</label><br>
        <select name="aprobado" id="aprobado">
            <option value="">Pendiente</option>
            <option value="aprobado" <?php selected($aprobado, 'aprobado'); ?>>Aprobado</option>
            <option value="rechazado" <?php selected($aprobado, 'rechazado'); ?>>Rechazado</option>
        </select>
    </p>
    <?php

    // Campo de Notas
    $notas = get_post_meta($post->ID, '_notas', true);
    ?>
    <p>
        <label for="notas">Notas (máx. 100 caracteres):</label><br>
        <textarea id="notas" name="notas" maxlength="100" style="width:100%;"><?php echo esc_textarea($notas); ?></textarea>
    </p>
    <?php
}

function rrhh_save_meta_boxes($post_id) {
    // Verificar nonce
    if (!isset($_POST['rrhh_estado_notas_nonce']) || !wp_verify_nonce($_POST['rrhh_estado_notas_nonce'], 'rrhh_estado_notas_nonce')) {
        return;
    }

    // Evitar auto guardado
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Verificar permisos del usuario
    if (isset($_POST['post_type']) && 'postulaciones_rrhh' === $_POST['post_type']) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    // Guardar el estado de aprobación/rechazo
    if (isset($_POST['aprobado'])) {
        sanitize_text_field($_POST['aprobado']);
        update_post_meta($post_id, '_aprobado', $_POST['aprobado']);
    }

    // Guardar las notas
    if (isset($_POST['notas'])) {
        $notas_sanitizadas = sanitize_textarea_field($_POST['notas']);
        update_post_meta($post_id, '_notas', substr($notas_sanitizadas, 0, 100)); // Aseguramos no más de 100 caracteres
    }
}
add_action('save_post', 'rrhh_save_meta_boxes');
