(function ($) {
    "use strict";
    $(function () {
        if ('countdown' in $.fn) {
            $('.azm-time-left .azm-time').each(function () {
                var $timer = $(this);
                if ($timer.data('countdownInstance') === undefined) {
                    $timer.countdown($timer.data('time'), function (event) {
                        $timer.find('.azm-days .azm-count').text(event.offset.totalDays);
                        $timer.find('.azm-hours .azm-count').text(event.offset.hours);
                        $timer.find('.azm-minutes .azm-count').text(event.offset.minutes);
                        $timer.find('.azm-seconds .azm-count').text(event.offset.seconds);
                    });
                }
            });
        }
    });
})(jQuery);
