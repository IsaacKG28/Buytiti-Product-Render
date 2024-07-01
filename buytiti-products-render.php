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

function mi_woo_productos_shortcode($atts) {
    // Establecer atributos por defecto
    $atts = shortcode_atts(
        array(
            'cantidad' => 6, // Número de productos por defecto
            'categoria' => '', // Categoría del producto (puede ser vacío)
            'slider' => 'no' // Por defecto, no mostrar como slider
        ),
        $atts,
        'mi_woo_productos' // Nombre del shortcode
    );

    // Convertir el valor del atributo 'cantidad' a un entero
    $cantidad = intval($atts['cantidad']);
    $mostrar_como_slider = $atts['slider'] === 'yes';

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
    $output = $mostrar_como_slider ? '<div class="buytiti-product-slider">' : '<div class="woo-products-grid">';


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
        $output .= '<span class="etiqueta-oferta">Remate</span>';
    }

    return $output;
}


// Registrar el shortcode
add_shortcode('mi_woo_productos', 'mi_woo_productos_shortcode');

// Función para encolar todos los scripts y estilos necesarios
if (!function_exists('mi_woo_productos_enqueue_scripts')) {
    function mi_woo_productos_enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('buytiti-productos-script', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), null, true);
        // JavaScript en línea para el hover de imagen, Slick Slider y añadir producto al carrito con AJAX
        $js = '
        jQuery(document).ready(function($) {
            // Hover de imagen
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

            // Inicializar Slick Slider si el contenedor existe
            if ($(".buytiti-product-slider").length) {
                $(".buytiti-product-slider").slick({
                    infinite: true,
                    autoplay: true,
                    autoplaySpeed: 3000,
                    speed: 300,
                    slidesToShow: 5,
                    slidesToScroll: 1,
                    responsive: [
                        {
                            breakpoint: 1590,
                            settings: {
                                slidesToShow: 4,
                                slidesToScroll: 1,
                                infinite: true,
                                autoplay: true,
                            }
                        },
                        {
                            breakpoint: 1366,
                            settings: {
                                slidesToShow: 3,
                                slidesToScroll: 1,
                                infinite: true,
                                autoplay: true,
                            }
                        },
                        {
                            breakpoint: 1024,
                            settings: {
                                slidesToShow: 3,
                                slidesToScroll: 1,
                                infinite: true,
                                autoplay: true,
                            }
                        },
                        {
                            breakpoint: 600,
                            settings: {
                                slidesToShow: 2,
                                slidesToScroll: 1,
                                infinite: true,
                                autoplay: true,
                            }
                        },
                        {
                            breakpoint: 480,
                            settings: {
                                slidesToShow: 2,
                                slidesToScroll: 1,
                                infinite: true,
                                autoplay: true,
                            }
                        }
                    ]
                });
            }

            // Añadir producto al carrito con AJAX y recargar la misma página
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

        wp_add_inline_script('buytiti-productos-script', $js);

        // Registrar y encolar el archivo CSS del plugin
        wp_register_style('buytiti-productos-style', false);
        wp_enqueue_style('buytiti-productos-style');
    }
}

// Agregar la función al gancho para encolar scripts
add_action('wp_enqueue_scripts', 'mi_woo_productos_enqueue_scripts');

// Agrega la función al gancho para encolar scripts y estilos
add_action( 'wp_enqueue_scripts', 'mi_woo_productos_inline_styles' );
function mi_woo_productos_enqueue_styles() {
    wp_enqueue_style( 'buytiti-products-render-style', plugin_dir_url( __FILE__ ) . 'buytiti-products-render.css' );
}

add_action( 'wp_enqueue_scripts', 'mi_woo_productos_enqueue_styles' );