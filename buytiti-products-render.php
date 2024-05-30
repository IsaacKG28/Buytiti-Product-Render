<?php
/**
* Plugin Name:       Buytiti - Products - Render
* Plugin URI:        https://buytiti.com
* Description:       Plugin para mostrar productos de un e-commerce
* Requires at least: 6.1
* Requires PHP:      7.0
* Version:           0.1.5
* Author:            Fernando Isaac González Medina
* License:           GPL-2.0
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:       buytitipluginproductos
*
* @package Buytiti
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
    // Salir si se accede directamente.
}

// Asegúrate de que WooCommerce está activo
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

function mi_woo_productos_shortcode( $atts ) {
    // Establecer atributos por defecto
    $atts = shortcode_atts(
        array(
            'cantidad' => 6, // Número de productos por defecto
            'categoria' => '', // Categoría del producto ( puede ser vacío )
        ),
        $atts,
        'mi_woo_productos' // Nombre del shortcode
    );

    // Convertir el valor del atributo 'cantidad' a un entero
    $cantidad = intval( $atts[ 'cantidad' ] );

    // Argumentos para la consulta de productos
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => $cantidad, // Usar la cantidad especificada
        'orderby'        => 'date',   // Ordenar por fecha
        'order'          => 'DESC',   // En orden descendente para los más recientes
        'post_status'    => 'publish', // Solo productos publicados
        'meta_query'     => array(
            array(
                'key'     => '_stock_status', // Clave de metadatos para el estado del stock
                'value'   => 'instock',       // Solo productos en stock
                'compare' => '=',            // Comparación de igualdad
            ),
        ),
    );

    // Agregar filtro de categoría si está definido
    if ( ! empty( $atts[ 'categoria' ] ) ) {
        $args[ 'tax_query' ] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $atts[ 'categoria' ],
            ),
        );
    }

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return 'No hay productos disponibles.';
        // Manejar caso sin productos
    }

    // Iniciar contenedor con estilo de cuadrícula
    $output = '<div class="woo-products-grid">';


    while ($query->have_posts()) {
        $query->the_post();
        $product = wc_get_product(get_the_ID());

        if (!$product) {
            continue;
        }

        $output .= '<div class="woo-product-item" id="product-' . get_the_ID() . '">';
        $product_link = get_permalink(get_the_ID());

        $image_url = '';
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
        }

        $secondary_image_url = '';
        $image_ids = $product->get_gallery_image_ids();
        if (!empty($image_ids)) {
            $secondary_image_url = wp_get_attachment_url($image_ids[0]);
        }

        $output .= '<a href="' . esc_url($product_link) . '" class="product-image-link">';
        if ($image_url) {
            $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr(get_the_title()) . '" class="primary-image">';
        } else {
            $output .= '<span>No hay imagen disponible</span>';
        }
        if ($secondary_image_url) {
            $output .= '<img src="' . esc_url($secondary_image_url) . '" alt="' . esc_attr(get_the_title()) . '" class="secondary-image" style="display:none;">';
        }
        $output .= '</a><br>';

        $output .= do_shortcode('[ti_wishlists_addtowishlist]');

        $published_date = get_the_date('U');
        $current_date = current_time('U');
        $days_since_published = ($current_date - $published_date) / DAY_IN_SECONDS;

        if ($days_since_published < 7) {
            $output .= '<span class="new-product">Nuevo</span>';
        }

        // $stock = $product->get_stock_quantity();
        // $output .= '<span class="' . esc_attr('stock-quantity-buytitisinapi') . '">Disponible: ' . esc_html($stock) . '</span><br>';

        $sale_price = $product->get_sale_price();
        $regular_price = $product->get_regular_price();

        $output .= get_product_labels($product, $sale_price);

        if ($sale_price && $regular_price > 0) {
            $descuento = (($regular_price - $sale_price) / $regular_price) * 100;
            $output .= '<span class="product-discount-buytiti">-' . round($descuento) . '%</span>';
        }

        $sku = $product->get_sku();
        if ($sku) {
            $output .= '<span class="' . esc_attr('sku-class-buytiti') . '">SKU: ' . esc_html($sku) . '</span><br>';
        }

        $marca = $product->get_attribute('Marca');
        if (!$marca) {
            $categorias = get_the_terms(get_the_ID(), 'product_cat');
            $categoria = $categorias ? $categorias[0]->name : '';
            $marca = 'BUYTITI - ' . $categoria;
        }
        $output .= '<span class="' . esc_attr('product-brand-buytiti') . '">' . esc_html($marca) . '</span><br>';

        $output .= '<a href="' . esc_url($product_link) . '" class="' . esc_attr('product-link-buytiti') . '">';
        $output .= '<strong class="' . esc_attr('product-title-buytiti') . '">' . esc_html(get_the_title()) . '</strong>';
        $output .= '</a><br>';

        if ($sale_price) {
            $output .= '<span class="precio"><del class="precio-regular-tachado">' . wc_price($regular_price) . '</del> <ins class="sale-price">' . wc_price($sale_price) . '</ins></span><br>';
        } else {
            $output .= '<span class="precio precio-regular">' . wc_price($regular_price) . '</span><br>';
        }

        $output .= '<form method="post" action="' . esc_url(wc_get_cart_url()) . '">';
        $output .= '<input type="hidden" name="add-to-cart" value="' . esc_attr(get_the_ID()) . '">';
        $output .= '<input type="hidden" name="product-anchor" value="product-' . esc_attr(get_the_ID()) . '">';
        $output .= '<input type="number" name="quantity" value="1" min="1" class="' . esc_attr('input-quantity-buytiti') . '" style="margin-right:10px;">';
        $output .= '<input type="submit" value="Añadir al carrito" class="button-compra-buytiti">';
        $output .= '</form>';

        $output .= '</div>';
    }

    wp_reset_postdata();
    $output .= '</div>';

    return $output;
}

function get_product_labels($product, $sale_price) {
    $output = '';
    $categorias = get_the_terms(get_the_ID(), 'product_cat');
    $esOfertaEnVivo = false;
    $esLiquidacion = false;

    if ($sale_price && $categorias) {
        foreach ($categorias as $categoria) {
            if ($categoria->name === 'Ofertas en Vivo') {
                $output .= '<span class="etiqueta-ofertas-en-vivo">Ofertas en Vivo</span>';
                $esOfertaEnVivo = true;
                break;
            }
        }
    }

    if ($sale_price && $categorias) {
        foreach ($categorias as $categoria) {
            if ($categoria->name === 'LIQUIDACIONES') {
                $output .= '<span class="etiqueta-liquidacionSinApi">LIQUIDACIÓN</span>';
                $esLiquidacion = true;
                break;
            }
        }
    }

    if ($sale_price && !$esOfertaEnVivo && !$esLiquidacion) {
        $output .= '<span class="etiqueta-oferta">Oferta</span>';
    }

    return $output;
}


// Registrar el shortcode
add_shortcode( 'mi_woo_productos', 'mi_woo_productos_shortcode' );

// Si estás agregando scripts relacionados con imágenes
if ( !function_exists( 'mi_woo_productos_enqueue_imagen_scripts' ) ) {
    function mi_woo_productos_enqueue_imagen_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'buytiti-productos-script', plugin_dir_url( __FILE__ ) . 'script.js', array( 'jquery' ), null, true );

        // JavaScript en línea para el hover de imagen
        $js = '
        jQuery(document).ready(function($) {
            $(".product-image-link").on("mouseover", function() {
                var primaryImage = $(this).find(".primary-image");
                var secondaryImage = $(this).find(".secondary-image");

                if (secondaryImage.length) {
                    var temp = primaryImage.attr("src");
                    primaryImage.attr("src", secondaryImage.attr("src"));
                    secondaryImage.attr("src", temp);
                }
            });

            $(".product-image-link").on("mouseleave", function() {
                var primaryImage = $(this).find(".primary-image");
                var secondaryImage = $(this).find(".secondary-image");

                if (secondaryImage.length) {
                    var temp = primaryImage.attr("src");
                    primaryImage.attr("src", secondaryImage.attr("src"));
                    secondaryImage.attr("src", temp);
                }
            });
        });
        ';

        wp_add_inline_script( 'buytiti-productos-script', $js );
    }
}

// Usa un nuevo nombre para la acción
add_action( 'wp_enqueue_scripts', 'mi_woo_productos_enqueue_imagen_scripts' );

// Script para añadir producto al carrito con AJAX y recargar la misma página

function mi_woo_productos_enqueue_scripts() {
    // Asegurarse de que WooCommerce tiene los parámetros necesarios para AJAX
    wp_enqueue_script( 'jquery' );
    // Asegurarse de que jQuery esté encolado
    $js = '
    jQuery(document).ready(function($) {
        $(".woo-add-to-cart").on("click", function(event) {
            event.preventDefault(); // Evitar el comportamiento predeterminado del formulario
            
            var product_id = $(this).data("product-id"); // Obtener el ID del producto
            var quantity = $(this).siblings("input[name=quantity]").val(); // Obtener la cantidad ingresada
            
            // Lógica AJAX para añadir al carrito
            $.ajax({
                url: wc_add_to_cart_params.ajax_url, // URL AJAX de WooCommerce
                method: "POST",
                data: {
                    action: "woocommerce_ajax_add_to_cart", // Acción para añadir al carrito
                    product_id: product_id,
                    quantity: quantity, // Añadir la cantidad al data
                },
                success: function(response) {
                    if (response.error) {
                        alert("Error al añadir al carrito: " + response.error);
                    } else {
                        // Si el producto se añadió correctamente, recargar la misma página
                        location.reload();
                    }
                },
                error: function() {
                    alert("Ocurrió un error al añadir al carrito.");
                }
            });
        });
    });
    ';
    wp_add_inline_script( 'jquery', $js );
    // Encolar el script en línea después de jQuery
}

// Agregar la función al gancho para encolar scripts
add_action( 'wp_enqueue_scripts', 'mi_woo_productos_enqueue_scripts' );

// Función para agregar el archivo CSS del plugin
function register_empty_style() {
    wp_register_style( 'buytiti-productos-style', false );
    wp_enqueue_style( 'buytiti-productos-style' );
}

add_action( 'wp_enqueue_scripts', 'register_empty_style' );

function mi_woo_productos_inline_styles() {
    // Aquí puedes definir tus estilos en línea
    $custom_css = "
    /* Estilos para el plugin de productos WooCommerce */
    .woo-products-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr); /* Seis columnas para el diseño de cuadrícula */
        gap: 20px; /* Espacio entre productos */
    }
    
    .woo-product-item {
        position: relative;
        text-align: center; /* Centrar el contenido */
        border: 1px solid #ddd; /* Bordes para los productos */
        padding: 10px;
        border-radius: 20px;
        height: 29.5rem;
        width: 100%;
    }
    
    .woo-product-item img {
        max-width: 140px; /* Limitar el tamaño de las imágenes */
        height: auto; /* Mantener la relación de aspecto */
        margin-top: 1.5rem;
        transition: 1s;
    }
    .woo-product-item img:hover{ 
    transform: scale(1.1);
    cursor: pointer;
    }

    .new-product{
        position: absolute; 
        top: 0; 
        left: 0; 
        z-index: 1;
        background-color: #f2fff6;
        border: 1px solid #058427;
        border-radius: 20px 0px 0px 0px;    
        color: #058427;
        width: 4rem;
        font-size: 1rem;
    }
    
    .etiqueta-liquidacionSinApi{
        position: absolute;
        top: 0;
        right: 0;
        background-color: #e4c311; 
        color: #ffffff; 
        padding: 3px;
        z-index: 1;  
        font-size: .9rem;
        font-weight: 700;
        border-radius: 0px 20px 0px 10px;
    }
    .etiqueta-ofertas-en-vivo{
        position: absolute;
    top: 0;
    right: 0;
    background-color: #F7CACA;
    color: red;
    padding: 3px;
    z-index: 1;
    font-size: .9rem;
    font-weight: 700;
    border-radius: 0px 20px 0px 10px;
    width: 8.5rem;
    }

    .etiqueta-ofertas-en-vivo::before {
    content: '•'; /* El punto */
    position: absolute;
    left: 0; /* Alineado a la izquierda */
    top: -5%; /* Centrar verticalmente */
    transform: translateY(-50%); /* Ajustar para centrar */
    color: red; /* Color del punto */
    animation: pulse 1s infinite; /* Aplica la animación de pulso */
    font-size: 2rem;
    margin-left: -.2rem;
    padding-left: .2rem;
}
@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2); /* Aumenta el tamaño */
        opacity: 0.7; /* Reduce la opacidad */
    }
    100% {
        transform: scale(1);
        opacity: 1; /* Vuelve al estado original */
    }
}
    
    .button-compra-buytiti{
           width: 9.9rem;
    justify-content: center;
    display: flex;
    margin-left: -2rem;
    align-items: center;
    background-color: #ef7e28 !important;
    height: 2.5rem;
    right: 0;
    position: absolute;
    border-radius: 20px 0px 20px 0px !important;
    bottom: 0;
    }
    
    .sku-class-buytiti{
      color: #00c9b7;
    font-size: .8rem;
    font-weight: 700;
    text-align: center;
    }
    
    .input-quantity-buytiti{ 
    position: absolute;
    left: 0;
    bottom: 0;
    border-radius: 0px 10px 0px 20px !important;
    height: 2rem !important;
    max-width: 70px !important;
    }
    
    .stock-quantity-buytitisinapi{ 
    background-color: #fde5cb;
    border: 1px solid #ff7942;
    border-radius: 15px 0 0 15px;
    color: coral;
    font-size: .6rem;
    font-weight: 700;
    right: 0;
    padding: 0 3px;
    width: 5.2rem;
    position: absolute;
    top: 41%;
    display: none;
    }
    
    .product-brand-buytiti {
    color: #8b8b8b !important;
    font-size: .7rem;
    font-weight: 600;
    letter-spacing: .3px;
    margin-top: -1.5rem;
    text-align: center;
    text-transform: uppercase;
}

.product-link-buytiti {
    text-decoration: none !important;
}

.product-title-buytiti {
    color: #5c5c5c !important;
    font-size: .9rem !important;
    overflow: hidden ;
    margin-top: -1rem ;
    font-weight: 700 !important;
    height: 5.5rem;
    display: inline-grid;
}

.precio-regular-tachado{
opacity: .8;
font-weight: 600;
}
.sale-price{
    font-size: 1.2rem;
    color: red;
    font-weight: 700;
}
.precio-regular{
    font-size: 1.2rem;
    font-weight: 700;
    color: #ff7942;
}
.product-discount-buytiti{
        position: absolute;
    top: 7%;
    right: 0;
    background-color: coral;
    color: #ffffff;
    padding: 3px;
    z-index: 1;
    font-size: .9rem;
    font-weight: 600;
    border-radius: 0px 0px 0px 10px;
    width: 3rem;
}

@media screen and (min-width: 201px) and (max-width: 400px) {
    .woo-products-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* Seis columnas para el diseño de cuadrícula */
        gap: 10px; /* Espacio entre productos */
    }
    .woo-product-item {
        height: 27.5rem;
        width: auto;
        margin-bottom: 1rem;
      }
      .input-quantity-buytiti{ 
    max-width: 60px !important;
    }
     .button-compra-buytiti{
    width: 7.7rem;
    font-size: 0.8rem !important;
  }
    .woo-product-item img {
        max-width: 110px;
    }
    .product-title-buytiti{
        height: 6.5rem;
    }
    .sku-class-buytiti{
        height: 1.2rem;
        display: grid;
        margin-top: .5rem;
    }
    .product-brand-buytiti {
        margin-top: 0rem;
        height: 1rem;
        display: grid;
    }
}

@media screen and (min-width: 401px) and (max-width: 600px) {
    .woo-products-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* Seis columnas para el diseño de cuadrícula */
        gap: 0px; /* Espacio entre productos */
    }
    .woo-product-item {
        height: 30.5rem;
        width: 98%;
        margin-bottom: 1rem;
    }
    .input-quantity-buytiti{ 
    max-width: 60px !important;
    }
     .button-compra-buytiti{
    width: 9.5rem;
    }
    .woo-product-item img {
        max-width: 110px;
    }
}

@media screen and (min-width: 601px) and (max-width: 780px) {
    .woo-products-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr); /* Seis columnas para el diseño de cuadrícula */
        gap: 0px; /* Espacio entre productos */
    }
    .woo-product-item {
        height: 32.5rem;
        width: 13rem;
        margin-bottom: 1rem;
    }
     .input-quantity-buytiti{ 
    max-width: 60px !important;
    }
     .button-compra-buytiti{
    width: 8.5rem;
    font-size: 0.85rem !important;
    }
}@media screen and (min-width: 781px) and (max-width: 1024px) {
    .woo-products-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr); /* Seis columnas para el diseño de cuadrícula */
        gap: 10px; /* Espacio entre productos */
    }
    .woo-product-item {
        width: 100%;
    }
    .button-compra-buytiti {
        width: 8rem;
    }
    .product-discount-buytiti{
        font-size: .69rem;
        width: 2rem;
    }
}
@media screen and (min-width: 1024px) and (max-width: 1420px) {
    .woo-products-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr); /* Seis columnas para el diseño de cuadrícula */
        gap: 10px; /* Espacio entre productos */
    }
    .woo-product-item {
        width: 100%;
    }
    .button-compra-buytiti {
        width: 7.77rem;
        font-size: 0.78rem !important;
    }
    .product-discount-buytiti{
        font-size: .69rem;
        width: 2rem;
    }

}

    ";

    // Asegúrate de que los estilos se añaden al final de la cola de estilos
    wp_add_inline_style( 'buytiti-productos-style', $custom_css );
}

// Agrega la función al gancho para encolar scripts y estilos
add_action( 'wp_enqueue_scripts', 'mi_woo_productos_inline_styles' );