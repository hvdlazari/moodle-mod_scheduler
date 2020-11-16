YUI.add('moodle-mod_scheduler-savegrade', function (Y, NAME) {

// ESLint directives.
/* eslint-disable camelcase */

var SELECTORS = {
        SELECTS: 'table#dates form.studentselectform select.studentselect'
    },
    MOD;

M.mod_scheduler = M.mod_scheduler || {};
MOD = M.mod_scheduler.savegrade = {};

/**
 * Save the "seen" status.
 *
 * @param {Number} cmid the coursemodule id
 * @param {Number} appid the id of the relevant appointment
 * @param {Boolean} newseen
 * @param {Object} spinner The spinner icon shown while saving
 */
MOD.save_status = function(cmid, appid, newgrade, spinner) {

    Y.io(M.cfg.wwwroot + '/mod/scheduler/ajax.php', {
        // The request paramaters.
        data: {
            action: 'savegrade',
            id: cmid,
            appointmentid: appid,
            grade: newgrade,
            sesskey: M.cfg.sesskey
        },

        timeout: 5000, // 5 seconds of timeout.

        // Define the events.
        on: {
            start: function() {
                spinner.show();
            },
            success: function() {
                window.setTimeout(function() {
                    spinner.hide();
                }, 250);
            },
            failure: function(transactionid, xhr) {
                var msg = {
                    name: xhr.status + ' ' + xhr.statusText,
                    message: xhr.responseText
                };
                spinner.hide();
                return new M.core.exception(msg);
            }
        },
        context: this
    });
};

MOD.init = function(cmid) {
    Y.all(SELECTORS.SELECTS).each(function(box) {
        box.on('change', function() {
            var spinner = M.util.add_spinner(Y, box.ancestor('div'));
            M.mod_scheduler.savegrade.save_status(cmid, box.getData('appid'), box.get('value'), spinner);
        });
    });
};


}, '@VERSION@', {"requires": ["base", "node", "event"]});
