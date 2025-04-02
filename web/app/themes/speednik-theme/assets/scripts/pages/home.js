const home = (() => {
  // This function returns another function that acts as your 'home' module.
  return () => {
    jQuery(document).ready(function ($) {
      // Inside here, $ can safely be used as an alias for jQuery.
      console.log('Homepage');
    });
  };
})();

export default home;