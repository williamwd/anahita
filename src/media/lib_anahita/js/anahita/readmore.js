/**
 * Author: Nick Swinford
 * Email: NicholasJohn16@gmail.com
 * Author: Rastin Mehr
 * Email: rastin@anahitapolis.com
 * Copyright 2015 rmdStudio Inc. www.rmdStudio.com
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */

;(function ($, window, document) {
    
    $('body').on('click', '[data-trigger="readmore"], [data-trigger="readless"]', function ( event ) {
        
        event.preventDefault();
        
        var elem = $(this);
        
        $('#' + elem.data('short')).toggle();
        
        $('#' + elem.data('full')).toggle();
    });
    
}(jQuery, window, document));

