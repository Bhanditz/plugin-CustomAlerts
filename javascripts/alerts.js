
var CustomAlerts = (function($) {

    var reportValuesAutoComplete = null;

    function updateMetrics(siteId) {
        if (!siteId) {
            siteId = $('[name=idSite]').val();
        }

        reportValuesAutoComplete = null;

        var ajaxRequest = new ajaxHelper();
        ajaxRequest.addParams({
            module: 'API',
            method: 'API.getReportMetadata',
            date: piwik.currentDateString,
            period: $(".period").val(),
            idSites: [siteId],
            format: 'JSON'
        }, 'GET');
        ajaxRequest.setCallback(function(data) {
            updateForm(data);
        });
        ajaxRequest.setErrorCallback(function () {});
        ajaxRequest.send(false);
    }

    function getValuesForReportAndMetric(request, response) {

        var metric = $('#metric').find('option:selected').val();

        function sendFeedback(values)
        {
            var matcher = new RegExp( $.ui.autocomplete.escapeRegex( request.term ), "i" );
            response( $.grep( values, function( value ) {
                if (!value) return false;

                value = value.label || value.value || value[metric] || value;
                return matcher.test( value );
            }) );
        }

        if ($.isArray(reportValuesAutoComplete)) {
            sendFeedback(reportValuesAutoComplete);
            return;
        }

        reportValuesAutoComplete = [];

        var report = $('#report').find('option:selected').val();

        if (!report) {
            return;
        }

        report = report.split('.');

        if (!metric || !$.isArray(report) || !report[0] || !report[1]) {
            sendFeedback(reportValuesAutoComplete);
        }

        var ajaxRequest = new ajaxHelper();
        ajaxRequest.addParams({
            module: 'API',
            method: 'API.getProcessedReport',
            date: 'yesterday',
            period: 'month',
            showColumns: metric,
            apiModule: report[0],
            apiAction: report[1],
            idSite: $('[name=idSite]').val(),
            format: 'JSON'
        }, 'GET');
        ajaxRequest.setCallback(function(data) {
            if (data && data.reportData) {
                reportValuesAutoComplete = data.reportData;
                sendFeedback(data.reportData);
            } else {
                sendFeedback([]);
            }
        });
        ajaxRequest.setErrorCallback(function () {
            sendFeedback([]);
        });
        ajaxRequest.send(false);
    }

    function updateForm(data) {

        var period = $('.period').val();

        $('.comparedToField select').hide();
        $('.comparedToField select').attr('data-inactive', 'data-inactive');
        $('.comparedToField select[data-period='+ period + ']').show();
        $('.comparedToField select[data-period='+ period + ']').removeAttr('data-inactive');

        currentGroup = $('.reports').val();
        options = "";
        for(var i = 0; i < data.length; i++)
        {
            value = data[i].module + '.' + data[i].action;
            if ('MultiSites.getOne' == value || 'MultiSites.getAll' == value) {
                continue;
            }

            if(currentGroup == undefined) {
                options += '<option selected="selected" value="' + value + '">' + data[i].name + '</option>';
                currentGroup = value;
            }
            else {
                options += '<option value="' + value + '">' + data[i].name + '</option>';
            }

            if(value == currentGroup)
            {
                metrics = data[i].metrics;

                mOptions = "";
                for(var metric in metrics)
                {
                    mOptions += '<option value="' + metric + '">' + metrics[metric] + '</option>';
                }
                $('.metrics').html(mOptions);

                if(data[i].dimension != undefined)
                {
                    $('#reportInfo').text(data[i].dimension);
                    $('.reportCondition').removeAttr('disabled');
                    $('.reportCondition').show();
                    $('.reportValue').removeAttr('disabled');
                    $('.reportValue').show();
                    $('.reportConditionField').show();
                    $('.reportValueField').show();
                }
                else
                {
                    $('#reportInfo').text("");
                    $('.reportCondition').attr('disabled', 'disabled');
                    $('.reportCondition').hide();
                    $('.reportValue').attr('disabled', 'disabled');
                    $('.reportValue').hide();
                    $('.reportConditionField').hide();
                    $('.reportValueField').hide();
                }
            }
        }
        $('.reports').html(options);
        $('.reports').val(currentGroup);

        updateReportCondition();
        updateMetricCondition();
    }

    function updateReportCondition()
    {
        if ('matches_any' == $('.reportCondition').val()) {
            $('span.reportConditionField').attr('colspan', '2');
            $('span.reportValueField').hide();
        } else {
            $('span.reportConditionField').attr('colspan', '');
            $('span.reportValueField').show();
        }
    }

    function updateMetricCondition()
    {
        var condition = $('#metricCondition').val();
        var metric = $('#metric').find('option:selected').val();

        var isPercentageCondition = condition && 0 === condition.indexOf('percentage_');
        var isPercentageMetric    = metric && -1 !== metric.indexOf('_rate');
        var isSecondsMetric = metric && -1 !== metric.indexOf('_time_');

        if (isPercentageCondition || isPercentageMetric) {
            $('.metricValueDescription').show();
            $('.metricValueDescription').text('%');
        } else if (isSecondsMetric) {
            $('.metricValueDescription').show();
            $('.metricValueDescription').text('s');
        } else {
            $('.metricValueDescription').hide();
        }
    }

    function deleteAlert(alertId)
    {
        function onDelete()
        {
            var ajaxRequest = new ajaxHelper();
            ajaxRequest.addParams({
                module: 'API',
                method: 'CustomAlerts.deleteAlert',
                idAlert: alertId,
                format: 'JSON'
            }, 'GET');
            ajaxRequest.redirectOnSuccess();
            ajaxRequest.send(false);
        }

        piwikHelper.modalConfirm('#confirm', {yes: onDelete});
    }

    $(document).ready(function() {

        updateReportCondition();
        updateMetricCondition();

        $('.alerts .period').change(function() {
            updateMetrics();
        });

        $('.alerts .reports').change(function() {
            updateMetrics();
        })

        $('.alerts #reportCondition').change(updateReportCondition)
        $('.alerts #metricCondition').change(updateMetricCondition)
        $('.alerts #metric').change(updateMetricCondition)

        var currentSiteId = $('[name=idSite]').val();
        $('.sites_autocomplete').bind('piwik:siteSelected', function (e, site) {
            if (site.id != currentSiteId) {
                currentSiteId = site.id;
                updateMetrics(site.id);
            }
        });

        $('.entityListContainer .deleteAlert[id]').click(function() {
            deleteAlert($(this).attr('id'));
        });

        if ($('.alerts .period').length) {
            updateMetrics();
        }

        $('.alerts #reportValue').autocomplete({
            source: getValuesForReportAndMetric,
            minLength: 1,
            delay: 300
        });

    });

    return {
        getApiParameters: function () {

            var mailSettings   = getReportParametersFunctions.email ? getReportParametersFunctions.email() : {additionalEmails: [], emailMe: false};
            var mobileSettings = getReportParametersFunctions.mobile ? getReportParametersFunctions.mobile() : {phoneNumbers: ['']};

            var idReport = $('#report_idreport').val();
            var apiParameters = {};
            apiParameters.format = 'json';
            apiParameters.name  = $('#alertName').val();
            apiParameters.metric  = $('#metric').find('option:selected').val();
            apiParameters.metricCondition  = $('#metricCondition').find('option:selected').val();
            apiParameters.metricValue = $('#metricValue').val();
            apiParameters.emailMe = mailSettings.emailMe ? 1 : 0;
            apiParameters.additionalEmails = (mailSettings.additionalEmails && mailSettings.additionalEmails.length) ? mailSettings.additionalEmails : [''];
            apiParameters.phoneNumbers = mobileSettings.phoneNumbers;
            apiParameters.report = $('#report').find('option:selected').val();
            apiParameters.reportCondition = $('#reportCondition').find('option:selected').val();
            apiParameters.reportValue  = $('#reportValue').val();
            apiParameters.idSites = [$('[name=idSite]').val()];
            apiParameters.comparedTo = $('[name=compared_to]:not([data-inactive])').val();

            return apiParameters;
        },

        isValidAlert: function (alert) {

            if (!$.isNumeric(alert.metricValue)) {
                var UI = require('piwik/UI');
                var notification = new UI.Notification();
                var options = {id: 'CustomAlertsMetricValueError', context: 'error', type: 'toast'};

                notification.show(_pk_translate('CustomAlerts_InvalidMetricValue'), options);
                $('#metricValue').css({backgroundColor: '#f2dede'});
                return false;
            }

            $('#metricValue').css({backgroundColor: '#ffffff'});
            return true;
        },

        sendApiRequest: function (method, POSTparams) {
            var period = $('#period').find('option:selected').val();

            var ajaxHandler = new ajaxHelper();
            ajaxHandler.addParams(POSTparams, 'POST');
            ajaxHandler.addParams({period: period, module: 'API', method: method}, 'GET');
            ajaxHandler.redirectOnSuccess({module: 'CustomAlerts', action: 'index'});
            ajaxHandler.send(true);
        }
    };

})($);