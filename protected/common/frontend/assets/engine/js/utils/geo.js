;var alto = alto || {};

/**
 * Гео-объекты
 */
alto.geo = (function ($) {
    "use strict";

    /**
     * Инициализация селектов выбора гео-объекта
     */
    this.initSelect = function () {
        $.each($('.js-geo-select'), function (k, v) {
            $(v).find('.js-geo-country').bind('change', function (e) {
                this.loadRegions($(e.target));
            }.bind(this));
            $(v).find('.js-geo-region').bind('change', function (e) {
                this.loadCities($(e.target));
            }.bind(this));
        }.bind(this));
    };

    this.loadRegions = function ($country) {
        var $region = $country.parents('.js-geo-select').find('.js-geo-region'),
            $city = $country.parents('.js-geo-select').find('.js-geo-city'),
            url = alto.routerUrl('ajax') + 'geo/get/regions/',
            params = {country: $country.val()};

        $region.empty();
        $region.append('<option value="">' + alto.lang.get('geo_select_region') + '</option>');
        $city.empty();
        $city.hide();

        if (!$country.val()) {
            $region.hide();
            return;
        }

        alto.ajax(url, params, function (result) {
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                $.each(result.aRegions, function (k, v) {
                    $region.append('<option value="' + v.id + '">' + v.name + '</option>');
                });
                $region.show();
                // *depricated* //ls.hook.run('ls_geo_load_regions_after', [$country, result]);
            }
        });
    };

    this.loadCities = function ($region) {
        var $city = $region.parents('.js-geo-select').find('.js-geo-city'),
            url = alto.routerUrl('ajax') + 'geo/get/cities/',
            params = {region: $region.val()};
        $city.empty();
        $city.append('<option value="">' + alto.lang.get('geo_select_city') + '</option>');

        if (!$region.val()) {
            $city.hide();
            return;
        }

        alto.ajax(url, params, function (result) {
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                $.each(result.aCities, function (k, v) {
                    $city.append('<option value="' + v.id + '">' + v.name + '</option>');
                });
                $city.show();
                // *depricated* //ls.hook.run('ls_geo_load_cities_after', [$region, result]);
            }
        });
    };


    return this;
}).call(alto.geo || {}, jQuery);