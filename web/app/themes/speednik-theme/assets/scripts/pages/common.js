// You can run a jQuery script with prefix $ in here directly since the main function in app.js was already wrapped in (function($){})(jQuery);
// You can name any functions here but its best that you name it based on the classname or page. 
const common = () => {
    // You can add your scripts here which is based on the classname in the body tag.
    jQuery(document).ready(function ($) {
        // Some functions needed when document is ready...

        $('.hidden-until-ready').removeClass('hidden-until-ready');


        // Generic Slick slider trigger, set 'slidesToShow' and 'slidesToScroll' in element data
        // Example: <div class="slick-slider" data-slick="{'slidesToShow': 3, 'slidesToScroll': 1}">
        // Or create a new $(element).slick() for specific slider setting
        $('.slick-slider').slick({
            autoplay: true,
            autoplaySpeed: 2000,
            dots: true,
            responsive: [ {
                breakpoint: 960,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    infinite: true,
                    dots: true
                }
            } ]
        });


    });
}

export  default  common;