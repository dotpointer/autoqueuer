<?php
header('Content-Type: text/javascript');
session_start();
require_once('functions.php');

# changelog
# 2015-06-05 17:39:01
# 2015-07-27 02:27:51 - adding email
# 2016-02-23 13:44:56 - replacing email field with email parameters, adding parameters gui
# 2016-02-23 15:43:56 - translations
# 2016-03-14 23:30:39 - translations
# 2016-08-26 19:59:28 - making it validate on jslint again
# 2016-08-26 20:15:19 - bugfix on translation after jslint validation
# 2017-07-31 14:17:10 - adding nickname
# 2017-09-10 23:56:00 - preview added, moving up changelog to php
# 2017-09-13 00:07:00 - adding chunk weights
# 2017-09-13 01:43:00 - adding cancel
# 2017-09-13 02:12:00 - adding ed2k to transfer list, moving progress bars
# 2017-09-21 23:12:00 - adding last modified to transfers
# 2017-09-22 00:08:00 - adding redownload
# 2017-09-22 00:32:00 - clarifying cancel dialogs
# 2017-09-23 00:13:00 - removing commented out code and extra newlines
# 2017-09-29 00:33:00 - removing row instead of reloading whole transfer list when cancelling a transfer
# 2018-03-22 00:17:00 - css adjustments
# 2018-03-22 01:52:00 - adding search links to transfers
# 2018-03-22 02:20:00 - replacing dots with spaces in search
# 2018-03-24 01:03:00 - adding incognito mode warning
# 2018-07-11 18:36:00 - adding login
# 2018-07-11 20:00:00 - es6 javascript conversion
# 2018-07-12 19:52:00 - jslint, updating jquery from 1.8.3 to 3.3.1
# 2018-07-13 19:31:26 - indentation change, tab to 2 spaces
# 2018-11-16 21:20:00 - adding sendmail setting to searches
# 2018-12-20 18:25:00 - moving translation to Base translate

start_translations(dirname(__FILE__).'/locales/');
?>
/*jslint white: true, this: true, browser: true, long: true */
/*global clientpumptypes,window,$,jQuery,toggler,Highcharts,files_queued_stats,
view,types,methods*/
let	e = {
  emule: {},
  incognito_mode: undefined,
  make: {},
  msg: <?php echo json_encode(get_translation_texts()); ?>,
  pages: { quickfind: {}	},
  requests: [],
  switch_page: null,
  timeouts: {},
  view: ""
};

(function() {
  "use strict";

  // add postJSON to jQuery
  jQuery.extend({
    postJSON: function (url, data, callback) {
      return jQuery.post(url, data, callback, "json");
    }
  });

  // run when document is ready
  $(window.document).ready(function() {

    // to translate texts
    e.t = function (s) {
      let found = false;
      // are the translation texts available?
      if (typeof e.msg !== "object") {
        return s;
      }

      // walk the translation texts
      Object.keys(e.msg).forEach(function (i) {
        if (
          found === false &&
          e.msg[0] !== undefined &&
          e.msg[1] !== undefined &&
          e.msg[i][0] === s
        ) {
          found = e.msg[i][1];
        }
      });

      if (found !== false) {
        return found;
      }

      return s;
    };

<?php if (is_logged_in()) { ?>
    // collection of useful tools
    e.tools = {

      // expected: yyyy-mm-dd hh:ii:ss
      format_date(s) {
        s = $.trim(s);
        s = s.substr(0, s.length - 3);
        s = s.substr(2, s.length);
        return s;
      },
      // parse int, radix 10 (decimal)
      pi10: (x) => parseInt(x, 10),
      replace_all(search, replacement, subject) {
        return subject.replace(new RegExp(
          search.replace(/[.*+?\^${}()|\[\]\\]/g, "\\$&"), "g"
        ), replacement);
      },
      // to split an ed2k link into parts
      split_ed2klink(ed2klink) {

        // get info for this file
        let fileinfo = ed2klink.split("|");

        // 0: protocol (crap)
        // 1: type
        // 2: filename
        // 3: size?
        // 4: ed2khash
        // 5: endslash (crap)

        // incomplete response?
        if (
          fileinfo[1] === undefined ||
          fileinfo[2] === undefined ||
          fileinfo[3] === undefined ||
          fileinfo[4] === undefined
        ) {
          // invalid response - ED2k-link is invalid, contents of it: '.$file['link']
          return false;
        }

        return {
          type: fileinfo[1],
          size: e.tools.pi10(fileinfo[3]),
          name: fileinfo[2],
          hash: fileinfo[4].toUpperCase()
        };
      },
      // to format seconds - taken from https://gist.github.com/remino/1563878
      format_seconds(s) {

        let d;
        let h;
        let m;

        m = Math.floor(s / 60);
        s = s % 60;
        h = Math.floor(m / 60);
        m = m % 60;
        d = Math.floor(h / 24);
        h = h % 24;
        return d + "d " + e.tools.pad_left(h,2,"0") + ":" +
          e.tools.pad_left(m,2,"0") + ":" +
          e.tools.pad_left(s,2,"0");
      },
      // to get file size - taken from stack overflow
      filesize(file_size_in_bytes) {
        let byte_units = [" kB", " MB", " GB", " TB", "PB", "EB", "ZB", "YB"];
        let ix = -1;

        do {
          file_size_in_bytes = file_size_in_bytes / 1024;
          ix = ix + 1;
        } while (file_size_in_bytes > 1024);

        return Math.max(file_size_in_bytes, 0.1).toFixed(1) + byte_units[ix];
      },
      // to pad a variable from the left
      pad_left(s, min, pad) {
        s = s.toString();
        while (s.length < min) {
          s = pad + s;
        }
        return s;
      },
      determine_incognito_mode() {
        let fs = window.RequestFileSystem || window.webkitRequestFileSystem;
        if (!fs) {
          return false;
        }
        fs(
          window.TEMPORARY,
          100,
          function () {
            e.incognito_mode = false;
          },
          function () {
            e.incognito_mode = true;
          }
        );
      },
      // to convert a timestamp to a date
      timestamp_to_date(timestamp) {
        let a = new Date(timestamp);
        return a.getFullYear() + "-" + e.tools.pad_left(a.getMonth() +
          1,2,"0")  + "-" + e.tools.pad_left(a.getDate(),2,"0") +
          " " + e.tools.pad_left(a.getHours(),2,"0") + ":" +
          e.tools.pad_left(a.getMinutes(),2,"0");
      },
      upperCaseFirstLetter: (s) => s.charAt(0).toUpperCase() + s.slice(1)
    };

    // functions for emule direct communication
    e.emule = {
      // to request a download
      request_download(id_clientpumps, id) {
        e.requests.push($.getJSON(
          "?format=json&action=quickfind_download&id_clientpumps=" + id_clientpumps + "&id=" + id,
          function (data) {
            if (!e.verify_response(data)) {
              return false;
            }
          }
        ));
        return true;
      },
      // to request a find
      request_find(input_data, callback_when_done) {
        input_data.action = "quickfind";

        e.requests.push($.postJSON(
          "?format=json",
          input_data,
          function (data) {
            // things below should treat the errors, therefore no error check here
            if (typeof callback_when_done === "function") {
              callback_when_done(data);
            }
          }
        ));
        return true;
      },
      // to get something
      get(what, params, callback_when_done) {
        let params_str = "";

        if (typeof params === "object") {
          Object.keys(params).forEach(function (ix) {
            if (params.hasOwnProperty(ix)) {
              params_str += "&" + ix + "=" + params[ix];
            }
          });
        }

        e.requests.push($.getJSON(
          "?format=json&view=" + what + params_str,
          function (data) {
            if (!e.verify_response(data)) {
              return false;
            }
            callback_when_done(data);
          }
        ));
        return true;
      }
    };
<?php } ?>
    // to lock a form
    e.lock_form = function (form, lock) {
      $(form).find("input,select,button,textarea")
        .each(function (index, element) {
          // if to pass jslint validation
          if (index > -1) {
            if (lock) {
              if (!$(element).attr("disabled")) {
                $(element)
                  .addClass("locked")
                  .attr("disabled", true);
              }
            } else {
              if ($(element).hasClass("locked")) {
                $(element).removeClass("locked").removeAttr("disabled");
              }
            }
          }
        });
    };
<?php if (is_logged_in()) { ?>
    // to render search result list
    e.pages.quickfind.render_results = function (searchresultlist) {
      let event_tr_click;
      let event_tr_dblclick;
      let fileinfo;

      $("#quickfind_results tbody")
        .empty();

      e.make.table_tr_loading("#quickfind_results tbody");

      event_tr_click = function (evt) {
        // is the row below us a slave row?
        if ($(evt.currentTarget).next().hasClass("slave")) {
          // then remove it
          $(evt.currentTarget).next().remove();
          $(evt.currentTarget).parents("tr:first").remove();
        // or is this not already downloaded?
        } else if (
          $(e.target).prop("data").download === undefined ||
          !$(evt.currentTarget).prop("data").download.length
        ) {
          $(e.target)
            .after(
              $("<tr/>")
                .addClass("slave")
                .append(
                  $("<td/>")
                    .attr("colspan", $(e.target).parents("table:first").find("thead th").length)
                    .append(
                      $("<button/>")
                        .click(function (evt) {
                          e.emule.request_download(
                            $(evt.currentTarget).parents("tr:first").prev().prop("data").id_clientpumps,
                            $(evt.currentTarget).parents("tr:first").prev().prop("data").id
                          );
                          $(evt.currentTarget).parents("tr:first").prev().addClass("downloading");
                          $(evt.currentTarget).parents("tr:first").remove();
                        })
                        .text(e.t("Download"))
                    )
                )
            );
        }
        return true;
      };

      event_tr_dblclick = function (evt) {
        e.emule.request_download(
          $(evt.currentTarget).data("data").id_clientpumps,
          $(evt.currentTarget).data("data").id
        );
        $(evt.currentTarget).addClass("downloading");
        return false;
      };

      Object.keys(searchresultlist).forEach(function (i) {
        if (searchresultlist.hasOwnProperty(i)) {
          if (searchresultlist[i].rowtype === undefined) {

            fileinfo = e.tools.split_ed2klink(searchresultlist[i].link);

            // did the split succeed?
            if (typeof fileinfo === "object") {

              // append a result row
              $("#quickfind_results tbody")
                .append(
                  $("<tr/>")
                    .click(event_tr_click)
                    .addClass((searchresultlist[i].download !== undefined && searchresultlist[i].download.length) ? "downloading" : "clickable")
                    .prop("data", searchresultlist[i])
                    .append(
                      $("<td/>").append(
                        $("<input/>")
                          .attr({
                            type: "checkbox",
                            name: "checked_ed2klinks[]"
                          })
                          .val(searchresultlist[i].filehash)
                      )
                    )
                    .append(
                      $("<td/>").text(fileinfo.name)
                    )
                    .append(
                      $("<td/>").text(e.tools.filesize(fileinfo.size)).attr("title", fileinfo.size)
                    )
                    .append(
                      $("<td/>").text(
                        searchresultlist[i].download
                      )
                    )
                    .dblclick(event_tr_dblclick)
                );
            }
          } else {

            // append a result row
            $("#quickfind_results tbody")
              .append(
                $("<tr/>")
                  .prop("data", searchresultlist[i])
                  .append(
                    $("<td/>")
                      .attr("colspan", $("#quickfind_results thead tr th").length)
                      .text(
                        searchresultlist[i].content
                      )
                  )
              );
          }
        }
      });

      $("#quickfind_results tbody tr.loading").remove();
    };

    // take an object or an array, iterate through it and return option elements
    e.obj_to_options = function (obj) {
      let tmp = $("<select/>");

      Object.keys(obj).forEach(function (i){
        if (obj.hasOwnProperty(i)) {
          tmp
            .append(
              $("<option/>")
                .val(i)
                .text(e.t(obj[i]))
            );
        }
      });

      return tmp.children();
    };

    e.timediff = function (current, previous) {
      let msPerMinute = 60 * 1000;
      let msPerHour = msPerMinute * 60;
      let msPerDay = msPerHour * 24;
      let msPerMonth = msPerDay * 30;
      let msPerYear = msPerDay * 365;
      let elapsed = current - previous;

      if (elapsed < msPerMinute) {
        return Math.round(elapsed/1000) + " " + e.t("seconds") + " " +
          e.t("ago");
      } else if (elapsed < msPerHour) {
        return Math.round(elapsed/msPerMinute) + " " + e.t("minutes") +
          " " + e.t("ago");
      } else if (elapsed < msPerDay ) {
        return Math.round(elapsed/msPerHour ) + " " + e.t("hours") + " " +
          e.t("ago");
      } else if (elapsed < msPerMonth) {
        return e.t("approximately") + " " + Math.round(elapsed/msPerDay) +
          " " + e.t("days") + " " + e.t("ago");
      } else if (elapsed < msPerYear) {
        return e.t("approximately") + " " + Math.round(elapsed/msPerMonth) +
          " " + e.t("months") + " " + e.t("ago");
      } else {
        return e.t("approximately") + " " + Math.round(elapsed/msPerYear ) +
          " " + e.t("years") + " " + e.t("ago");
      }
    };

    // to make a loading row
    e.make.table_tr_loading = function (tbody) {
      $(tbody).append(
        $("<tr/>")
          .addClass("loading")
          .append(
            $("<td/>")
              .attr("colspan", $(tbody).parent("table").find("thead th").length)
              .append(
                $("<img/>")
                  .attr("src", "img/loading_16x16_black.gif")
              )
          )
      );
    };

    // to make a table in the contents
    e.make.table = function (id, columns, loading) {
      let thead_tr = $("<tr/>");

      loading = (loading === undefined) ? true : loading;

      // walk columns and construct a header row
      Object.keys(columns).forEach(function (i){
        if (columns.hasOwnProperty(i)) {
          if (typeof columns[i] === "object") {
            thead_tr.append(
              $("<th/>")
                .addClass(columns[i].classes !== undefined ? columns[i].classes : "")
                .text(
                  columns[i].inner_html !== undefined
                  ?
                  columns[i].inner_html
                  :
                  ""
                )
              );
          } else {
            thead_tr.append(
              $("<th/>").text(columns[i])
            );
          }
        }
      });

      // append table
      $("#content")
        .append(
          $("<table/>")
            .attr("id",id)
            .append(
              $("<thead/>")
                .append(
                  thead_tr
                )
            )
            .append(
              $("<tbody/>")
            )
        );

      // add loading row?
      if (loading) {
        e.make.table_tr_loading("#" + id + " tbody");
      }

      return true;
    };

    // to make a table tr
    e.make.table_tr = function (tbody, data, columns, callback_maker) {
      let tr = $("<tr/>").prop("data", data);

      // walk columns
      Object.keys(columns).forEach(function (i){
        if (columns.hasOwnProperty(i)) {

          // what type of data is this column?
          switch (typeof columns[i]) {
            case "object": // an object, possibly containing html and class
              // is there an inner_html section available?
              if (columns[i].inner_html !== undefined) {
                // then append it as it is html
                tr
                  .append(
                    $("<td/>")
                      .addClass(columns[i].classes !== undefined ? columns[i].classes : "")
                      .append(
                        columns[i].inner_html !== undefined
                        ?
                        columns[i].inner_html
                        :
                        ""
                      )
                  );
              } else {
                tr
                  .append(
                    $("<td/>").append(columns[i])
                  );
              }

              break;
            default:
              tr
                .append(
                  $("<td/>").append(columns[i])
                );
          }
        }
      });

      if (typeof callback_maker === "function") {
        tr = callback_maker(data, tr);
      }

      $(tbody)
        .append(tr);

      return tr;
    };
<?php } ?>
    // to make a table in the contents
    e.make.textbox = function (id, text) {
      // append table
      $("#content")
        .append(
          $("<p/>")
            .attr("id",id)
            .append(
              text
            )
        );

      return true;
    };

    // to verify response from server
    e.verify_response = function (data) {
      // make sure response is an object

      if (
        data === undefined ||
        data === null ||
        typeof data !== "object" ||
        typeof data.status !== "string"
      ) {
        window.alert(e.t("Invalid response from server."));
        return false;
      }

      // find out what the status was
      switch (data.status) {
        case "error":
          // is there a data container with a message inside?
          if (typeof data.data === "object" && data.data.message !== undefined) {
            // then give that to the user
            window.alert(e.t("Error") + ": " + data.data.message);
          } else {
            window.alert(e.t("Unspecified error reported from server."));
          }
          return false;
        case "ok":
          return true;
        default:
          window.alert(
            e.t("Unspecified status reported from server") +
            ": " + data.status
          );
          return false;
      }
    };

    // to reload page
    e.reload_page = function () {
      if (e.view.length) {
        return e.switch_page(e.view);
      }
      return false;
    };

    // to switch page
    e.switch_page = function (view) {

      // walk previous requests and abort them all
      Object.keys(e.requests).forEach(function (l) {
        e.requests[l].abort();
      });
      e.requests = [];

      // clean content container
      $("#content").empty();

      e.view = view;

      // find out what view that was requested
      switch (view) {
<?php if (is_logged_in()) { ?>
        case "clientpumps":
          // do a descriptive text
          e.make.textbox("", e.t(
            "Client pumps are the collection name for the " +
            "underlaying downloading softwares that are remoted " +
            "to download the content you want. Supported client " +
            "pump softwares are") + " eMule xTreme Mod " +
            e.t("and") + " mlnet. " + e.t("On this page you " +
            "setup the connection to these, multiple ones may " +
            "be configured and controlled. Note that you need to " +
            "setup the softwares itself too."
          ));

          // do a table
          e.make.table("clientpumps", [
            "#",
            e.t("Type"),
            e.t("Host"),
            e.t("Port"),
            e.t("Username"),
            e.t("Password"),
            e.t("Searched"),
            e.t("Searches"),
            e.t("Queued files"),
            e.t("Active"),
            e.t("Manage")
          ]);

          // make a search insert/update form
          $("#content")
            .append(
              $("<form/>")
                .attr("id", "insert_or_update_clientpump_form")
                .append(
                  $("<fieldset/>")
                    .append(
                      $("<label/>").text("#:")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr({
                          name: "id_clientpumps",
                          type: "text",
                          readonly: true
                        })
                    )
                    .append(
                      $("<a/>")
                        .attr("href", "#")
                        .text(e.t("New client pump"))
                        .click(function (evt) {
                          $(evt.currentTarget).parents("form:first")[0].reset();
                          evt.preventDefault();
                          return false;
                        })
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Type") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","type")
                        .append(
                          e.obj_to_options(clientpumptypes)
                        )
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Host") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","host")
                    )
                    .append(
                      $("<br/>")
                    )
                  .append(
                      $("<label/>").text( e.t("Port") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","port")
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Username") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","username")
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Password") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr({
                          name: "password",
                          type: "password"
                        })
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Incoming path") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","path_incoming")
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Status") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","status")
                        .append(
                          e.obj_to_options({
                            "0": e.t("Inactive"),
                            "1": e.t("Active")
                          })
                        )
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<button/>")
                        .addClass("marginated")
                        .text(e.t("Save"))
                        .attr("id", "button_submit_insert_or_update_search")
                    )
                )
            );

          e.lock_form("#insert_or_update_clientpump_form", true);

          $("#insert_or_update_clientpump_form").submit(function (evt) {
            let data = $(evt.currentTarget).serializeArray();
            let post = {
                action: "insert_or_update_clientpump"
              };

            // lock down the form
            e.lock_form(evt.currentTarget, true);

            // flatten serialized array
            Object.keys(data).forEach(function (i){
              post[data[i].name] = data[i].value;
            });

            e.requests.push($.postJSON(".", post, function (data) {
              if (!e.verify_response(data)) {
                e.lock_form("#insert_or_update_clientpump_form", false);
                return false;
              }
              e.lock_form("#insert_or_update_clientpump_form", false);
              e.reload_page();
            }));

            evt.preventDefault();
            return false;
          });

          // get searches
          e.emule.get("clientpumps", "", function (data) {

            let event_a_delete;
            let event_a_edit;
            let div_manage = null;

            // when clicking on delete links
            event_a_delete = function (evt) {
              // get row data
              let rowdata = $(evt.currentTarget).parents("tr:first").prop("data");

              if (window.confirm(e.t("Are you sure that you want to delete") + " \"" + rowdata.type + "@" + rowdata.host + "\" (#" + rowdata.id + ")?")) {
                e.requests.push($.getJSON("./?action=delete_clientpump&id_clientpumps=" + rowdata.id, function (d) {
                  if (!e.verify_response(d)) {
                    return false;
                  }
                  e.reload_page();
                }));
              }

              evt.preventDefault();
              return false;
            };

            // when clicking on edit links
            event_a_edit = function (evt) {

              // get row data
              let rowdata = $(evt.currentTarget).parents("tr:first").prop("data");

              // load form with data
              $("#insert_or_update_clientpump_form input[name='host']").val(rowdata.host);
              $("#insert_or_update_clientpump_form input[name='id_clientpumps']").val(rowdata.id);
              $("#insert_or_update_clientpump_form input[name='password']").val("********");
              $("#insert_or_update_clientpump_form input[name='path_incoming']").val(rowdata.path_incoming);
              $("#insert_or_update_clientpump_form input[name='port']").val(rowdata.port);
              $("#insert_or_update_clientpump_form input[name='username']").val(rowdata.username);
              $("#insert_or_update_clientpump_form select[name='status']").val(rowdata.status);
              $("#insert_or_update_clientpump_form select[name='type']").val(rowdata.type);
              evt.preventDefault();
              return false;
            };

            // walk clientpumps
            Object.keys(data.data.clientpumps).forEach(function (i){

              div_manage = $("<div/>")
                .append(
                  $("<a/>")
                    .attr("href", "#")
                    .addClass("button")
                    .text(e.t("Edit"))
                    .click(event_a_edit)
                )
                .append(" ")
                .append(
                  $("<a/>")
                    .attr("href", "#")
                    .addClass("button")
                    .text(e.t("Remove"))
                    .click(event_a_delete)
                );

              // make TR
              e.make.table_tr("#clientpumps tbody", data.data.clientpumps[i],
              [
                data.data.clientpumps[i].id,
                data.data.clientpumps[i].type,
                data.data.clientpumps[i].host,
                data.data.clientpumps[i].port,
                data.data.clientpumps[i].username,
                "********",// password_container,
                {inner_html: e.tools.format_date(data.data.clientpumps[i].searched), classes: "date"},
                {inner_html: data.data.clientpumps[i].searches, classes: "counter"},
                {inner_html: data.data.clientpumps[i].queuedfiles, classes: "counter"},
                data.data.clientpumps[i].status === 1 ? e.t("On") : e.t("Off"),
                {inner_html: div_manage.children(), classes: "manage"}
              ], function (data, tr) {

                if (data.status === 1) {
                  tr.addClass("active");
                } else {
                  tr.addClass("inactive");
                }

                return tr;

              });
            });

            // remove loading
            $("#clientpumps tbody tr.loading").remove();

            // unlock form
            e.lock_form("#insert_or_update_clientpump_form", false);
          });

          return true;
<?php } ?>
        case "login":

          e.make.textbox("", e.t("Login here."));

          // do the form
          $("#content")
            .append(
              $("<form/>")
                .attr("id", "login_form")
                .append(
                  $("<fieldset/>")

                    .append(
                      $("<label/>").text(e.t("Username") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","username")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("The username to login with."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Password") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr({
                          name: "password",
                          type: "password"
                        })
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("The password to login with."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<button/>")
                        .addClass("marginated")
                        .text(e.t("Login"))
                        .attr("id", "button_submit_insert_or_update_parmeters")
                    )
                    .append(
                      $("<br/>")
                    )
                )
            )<?php
if (defined('ID_VISUM') && constant('ID_VISUM') !== false && defined('BASE_DOMAINNAME') && constant('BASE_DOMAINNAME') !== false) { ?>
            .append(
              $("<br/>")
            )
            .append(
              $("<p/>")
                .append(
                  $("<a/>")
                    .attr("href", "http://www.<?php echo BASE_DOMAINNAME?>/?section=visum&id_sites=<?php echo ID_VISUM?>")
                    .text(e.t("Login with Visum here."))
                )
            )<?php
} ?>;
          $("#login_form").submit(function (evt) {
            let data = $(evt.currentTarget).serializeArray();
            let post = {
              action: "login",
              format: "json",
              logintype: "local"
            };

            // lock down the form
            e.lock_form(evt.currentTarget, true);

            // flatten serialized array
            Object.keys(data).forEach(function (i) {
              post[data[i].name] = data[i].value;
            });

            e.requests.push($.postJSON(".", post, function (data) {
              if (!e.verify_response(data)) {
                e.lock_form("#login_form", false);
                return false;
              }
              e.lock_form("#login_form", false);
              window.location = ".";
            }));

            evt.preventDefault();
            return false;
          });

          return true;
<?php if (is_logged_in()) { ?>
        case "logout":

            e.requests.push(
              $.postJSON(
              ".",
              {
                action: "logout"
              },
              function (data) {
                if (!e.verify_response(data)) {
                  return false;
                }
                window.location = ".";
              }
            ));

            return false;
        case "quickfind":

          e.make.textbox("", e.t("Here you can do a direct search using the configured client pump softwares."));

          // make a search form
          $("#content")
            .append(
              $("<form/>")
                .attr("id", "quickfind_find_form")
                .append(
                  $("<fieldset/>")

                    .append(
                      $("<label/>").text(e.t("Client pump") + ":")

                    )
                    .append(
                      $("<select/>")
                        .attr("name","id_clientpumps")
                        .append(
                          $("<option/>")
                            .val("")
                            .text(e.t("Loading") + "...")
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Choose the program used to do the download."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Search") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","search")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Specify the search words here."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Type") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","type")
                        .append(
                          $("<option/>")
                            .val("")
                            .text(e.t("All"))
                        )
                        .append(
                          e.obj_to_options(types)
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Choose the desired content type."))
                    )
                    .append(
                      $("<br/>")
                    )
                    /*
                    .append(
                      $("<label/>").text(e.t("Method") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","method")
                        .append(
                          e.obj_to_options(methods)
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Choose the used method."))
                    )
                    .append(
                      $("<br/>")
                    )
                    */
                    .append(
                      $("<label/>").text(e.t("Size min") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","sizemin")
                    )
                    .append(
                      $("<span/>")
                        .text("MB")
                        .addClass("suffix")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Enter the minimum file size."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Size max") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","sizemax")
                    )
                    .append(
                      $("<span/>")
                        .text("MB")
                        .addClass("suffix")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Enter the maximum file size."))
                    )
                    .append(
                      $("<br/>")
                    )
                    /*
                    .append(
                      $("<label/>").text(e.t("Extension") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","extension")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Specify the file extension."))
                    )
                    .append(
                      $("<br/>")
                    )
                    */
                    .append(
                      $("<label/>").text(e.t("Show downl.") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","show_download")
                        .append(
                          e.obj_to_options({
                            "0": e.t("No"),
                            "1": e.t("Yes")
                          })
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("To show the results directly or not, if not you may click Update to refresh."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<button/>")
                        .addClass("marginated")
                        .text(e.t("Search"))
                        .attr("id", "button_submit_quickfind")
                    )
                    .append(" ")
                    .append(
                      $("<button/>")
                        .text(e.t("Update"))
                        .attr("id", "button_update_quickfind")
                    )
                )
            );

          // lock it
          e.lock_form("#quickfind_find_form", true);

          // get current client pumps
          e.emule.get("clientpumps", {}, function (data) {
            // invalid status?
            if (data.status !== "ok") {
              if (data.status === "error") {
                window.alert(data.data.message);
              } else {
                window.alert(e.t("Bad response from server"));
              }
            }

            // walk client pumps
            Object.keys(data.data.clientpumps).forEach(function (i){
              // is this one available?
              if (data.data.clientpumps[i].status === 1) {
                // then add it to list of pumps
                $("#quickfind_find_form select[name=\"id_clientpumps\"]").append(
                  $("<option/>")
                    .text(data.data.clientpumps[i].type + "@" + data.data.clientpumps[i].host)
                    .val(data.data.clientpumps[i].id)
                );
              }
            });

            // remove loader option
            $("#quickfind_find_form select[name=\"id_clientpumps\"] option[value=\"\"]").remove();

            e.lock_form("#quickfind_find_form", false);
          });

          e.make.table("quickfind_results", ["",e.t("Name"), e.t("Size"), e.t("Info")], false);

          // when submitting the quickfind form
          $("#quickfind_find_form").submit(function (evt) {

            // lock down the form
            e.lock_form(evt.currentTarget, true);

            // request the find
            e.emule.request_find({
              // extension:	$("input[name='extension']").val(),
              // method: 	$("select[name='method']").val(),
              id_clientpumps:	$("select[name='id_clientpumps']").val(),
              search:			$("input[name='search']").val(),
              show_download:	$("select[name='show_download']").val(),
              sizemax:		$("input[name='sizemax']").val(),
              sizemin:		$("input[name='sizemin']").val(),
              type: 			$("select[name='type']").val()
            }, function (data) {

              if (!e.verify_response(data)) {
                e.lock_form("#quickfind_find_form", false);
                return false;
              }

              // when done
              e.pages.quickfind.render_results(data.data.searchresultlist);
              e.lock_form("#quickfind_find_form", false);
            });

            evt.preventDefault();
            return false;
          });

          // when clicking on the update results button
          $("#button_update_quickfind").click(function () {
            e.lock_form("#quickfind_find_form", true);

            $("#quickfind_results tbody")
              .empty();

            e.make.table_tr_loading("#quickfind_results tbody");

            // fetch results
            e.emule.get("quickfind_results", {
                id_clientpumps: $("select[name='id_clientpumps']").val(),
                show_download: $("select[name='show_download']").val()
              }, function (data) {
              e.pages.quickfind.render_results(data.data.searchresultlist);
              e.lock_form("#quickfind_find_form", false);
            });
            return false;
          });

          return true;

        case "transfers":

          e.make.textbox("", e.t("Here is an overview of the files that are currently downloading."));

          // make a table
          e.make.table("transfers", [
            e.t("Preview"),
            "%",
            e.t("Name"),
            {inner_html: e.t("Type"), classes: "unimportant"},
            e.t("Size"),
            {inner_html: e.t("Completed"), classes: "unimportant"},
            e.t("Speed"),
            {inner_html: e.t("State"), classes: "unimportant"},
            e.t("Actions")
          ]);

          // fetch results
          e.emule.get("transfers", {version: 2}, function (data) {

            let percentage;
            let chunkbar = null;
            let progressbar;
            let search_links = [];

            // invalid status?
            if (data.status !== "ok") {
              if (data.status === "error") {
                window.alert(data.data.transfers.message);
              } else {
                window.alert(e.t("Bad response from server"));
              }
            }

            if (data.data.search_links) {
              try {
                search_links = JSON.parse(data.data.search_links);
              } catch (evt) {
                // if to pass jslint because evt must be used
                if (evt !== null) {
                  search_links = [];
                }
              }
            }

            Object.keys(data.data.transfers).forEach(function (i){
              let search_links_container = $("<div/>");

              if (data.data.transfers[i].chunkweights !== undefined) {
                chunkbar = $("<div/>").addClass("chunkbar");
                Object.keys(data.data.transfers[i].chunkweights).forEach(function (j) {
                  chunkbar.append(
                    $("<div/>")
                      .addClass("chunkweight" + data.data.transfers[i].chunkweights[j].type)
                      .css("width", data.data.transfers[i].chunkweights[j].weight + "%")
                  );
                });
              }

              progressbar = $("<div/>").addClass("progressbar");
              percentage = data.data.transfers[i].completed;

              progressbar
              .append(
                $("<div/>").addClass("progressbar_text").text(percentage)
              )
              .append(
                $("<div/>").addClass("progressbar_bar").css("width", percentage)
              )
              .after(
                chunkbar
              );

              if (search_links.length) {
                search_links.forEach(function (item) {
                  search_links_container.append(
                    $("<a/>")
                      .addClass("unimportant")
                      .attr("href", "#")
                      .prop("name", data.data.transfers[i].name)
                      .prop("url", item.url)
                      .click(function (evt) {
                        if (!e.incognito_mode) {
                          e.incognito_mode = window.confirm(e.t("You are not in incognito mode. This link will register in the browser history. Do you want to continue anyway? (This will disable the warning for this session.)"));
                        }

                        if (e.incognito_mode) {
                          let tmp = $(evt.currentTarget).prop("name");
                          let url;
                          tmp = data.indexOf(".") !== -1 ? data.substring(0, data.lastIndexOf(".")) : data;
                          tmp = e.tools.replace_all("\\_", " ", data);
                          tmp = e.tools.replace_all("\\-", " ", data);
                          tmp = e.tools.replace_all("\\(", " ", data);
                          tmp = e.tools.replace_all("\\)", " ", data);
                          tmp = e.tools.replace_all("\\#", " ", data);
                          tmp = e.tools.replace_all(".", " ", data);

                          url = $(evt.currentTarget).prop("url").replace("###NAME###", encodeURIComponent($.trim(tmp)));

                          window.open(url);
                        }

                        evt.preventDefault();
                        return false;
                      })
                      .text(item.title)
                  );
                });
              }

              e.make.table_tr("#transfers tbody", data.data.transfers[i], [
                $("<div/>")
                  .addClass("previewcontainer")
                  .append(
                    data.data.transfers[i].preview
                    ?
                      $("<a/>")
                        .attr({
                          alt: e.t("Preview of") + " " + data.data.transfers[i].name,
                          href: "?view=preview&id_clientpumps=" + data.data.transfers[i].id_clientpumps + "&filehash=" + data.data.transfers[i].ed2k,
                          title: e.t("Preview of") + " " + data.data.transfers[i].name
                        })
                        .append(
                          $("<img/>")
                            .attr("src", "?view=preview&id_clientpumps=" + data.data.transfers[i].id_clientpumps + "&filehash=" + data.data.transfers[i].ed2k)
                            .addClass("preview")
                        )
                        .click(function (evt) {

                          let opened = window.open($(evt.currentTarget).attr("href"), "_blank");

                          if (opened) {
                            opened.focus();
                          } else {
                            window.alert(e.t("Failed opening new window, popups may be blocked."));
                          }
                          evt.preventDefault();
                          return false;
                        })
                    : ""
                  )
                  .append(data.data.transfers[i].modified ? "<br>" : "")
                  .append(
                    data.data.transfers[i].modified ?
                    $("<span/>")
                      .addClass(Date.now() - Date.parse(data.data.transfers[i].modified) < 86400 * 1000 ? "hot" : "")
                      .attr("title", e.t("Was updated") + " " + data.data.transfers[i].modified)
                      .append(
                        e.timediff(Date.now(), Date.parse(data.data.transfers[i].modified))
                      )
                    : ""
                  ),
                progressbar,
                $("<span/>")
                  .text(data.data.transfers[i].name)
                  .after("<br>")
                  .after(
                    $("<span/>").text(data.data.transfers[i].ed2k).addClass("ed2k")
                  )
                  .after(" ")
                  .after(
                    search_links_container.children()
                  ),
                {inner_html: data.data.transfers[i].type, classes: "unimportant"},
                data.data.transfers[i].sizetotal,
                {inner_html: data.data.transfers[i].sizecompleted, classes: "unimportant"},
                data.data.transfers[i].speed,
                {inner_html: data.data.transfers[i].downstate, classes: "unimportant"},
                $("<div/>")
                  .append(
                    data.data.transfers[i].actions.indexOf("cancel") !== -1
                      ?
                      $("<a/>")
                        .attr({
                          href: "#"
                        })
                        .addClass("button")
                        .prop("id", data.data.transfers[i].id)
                        .prop("id_clientpumps", data.data.transfers[i].id_clientpumps)
                        .prop("name", data.data.transfers[i].name)
                        .click(function (evt) {
                            let row = $(evt.currentTarget).parents("tr:first");
                            event.preventDefault();

                            if ($(evt.currentTarget).prop("locked")) {
                              return false;
                            }

                            if (!window.confirm(e.t("Are you sure that you want to cancel") + " \""+ $(evt.currentTarget).prop("name") + "\" (" + $(evt.currentTarget).prop("id_clientpumps") + " / " + $(evt.currentTarget).prop("id") + ")?")) {
                              return false;
                            }

                            $(evt.currentTarget).prop("locked", true);

                            e.requests.push($.getJSON(
                              "?format=json&action=cancel&id_clientpumps=" + $(evt.currentTarget).prop("id_clientpumps") + "&id=" + $(evt.currentTarget).prop("id"),
                              function (data) {
                                if (!e.verify_response(data)) {
                                  return false;
                                }
                                row.remove(); // not sure if it is trustable that
                                // pump does not reuse it is ids when items are removed
                                // e.reload_page();
                              }
                            ));

                            return false;
                          })
                        .text(e.t("Cancel"))
                      :
                      ""
                  )
                  .append(data.data.transfers[i].actions.indexOf("cancel") !== -1 ? "<br/>" : "")
                  .append(
                    data.data.transfers[i].actions.indexOf("cancel") !== -1
                      ?
                      $("<a/>")
                        .attr({
                          href: "#"
                        })
                        .addClass("button")
                        .prop("id", data.data.transfers[i].id)
                        .prop("ed2k", data.data.transfers[i].ed2k)
                        .prop("id_clientpumps", data.data.transfers[i].id_clientpumps)
                        .prop("name", data.data.transfers[i].name)
                        .click(function (evt) {
                            let row = $(evt.currentTarget).parents("tr:first");
                            event.preventDefault();

                            if ($(evt.currentTarget).prop("locked")) {
                              return false;
                            }

                            if (!window.confirm(e.t("Are you sure that you want to cancel") + " \"" + $(evt.currentTarget).prop("name") + "\" (" + $(evt.currentTarget).prop("id_clientpumps") + " / " + $(evt.currentTarget).prop("id") + ")?" + "\n\n" + e.t("The file will be allowed to redownload if it re-appears."))) {
                              return false;
                            }

                            $(evt.currentTarget).prop("locked", true);

                            e.requests.push($.getJSON(
                              "?format=json&action=cancel&id_clientpumps=" + $(evt.currentTarget).prop("id_clientpumps") + "&id=" + $(evt.currentTarget).prop("id") + "&redownload=1&filehash=" + $(evt.currentTarget).prop("ed2k"),
                              function (data) {
                                if (!e.verify_response(data)) {
                                  return false;
                                }
                                row.remove(); // not sure if it is trustable that
                                // pump does not reuse it is ids when items are removed
                                // e.reload_page();
                              }
                            ));

                            return false;
                          })
                        .text(e.t("Cancel/Redownload"))
                      :
                      ""
                  )
              ]);
            // }
            });

            // remove loading
            $("#transfers tbody tr.loading").remove();
          });

          return true;

        case "searches":

          e.make.textbox(false, e.t("On this page you manage the scheduled searches that are carried out automatically so you can do other things."));

          // do a table
          e.make.table("searches", [
            e.t("Search"),
            e.t("Nickname"),
            e.t("Type"),
            e.t("Min"),
            e.t("Max"),
            e.t("Ext."),
            e.t("Method"),
            e.t("Timeout"),
            e.t("Executed"),
            e.t("Researchable"),
            e.t("Execs."),
            e.t("Files"),
            e.t("Mail"),
            e.t("Status"),
            e.t("Manage")
          ]);

          // make a search insert/update form
          $("#content")
            .append(
              $("<form/>")
                .attr("id", "insert_or_update_search_form")
                .append(
                  $("<fieldset/>")
                    .append(
                      $("<label/>").text("#:")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr({
                          name: "id_searches",
                          type: "text",
                          readonly: true
                        })
                    )
                    .append(
                      $("<a/>")
                        .attr("href", "#")
                        .text(e.t("New search"))
                        .click(function (evt) {
                          $(evt.currentTarget).parents("form:first")[0].reset();
                          $("input[name=\"executiontimeoutbase\"],input[name=\"executiontimeoutrandbase\"]").val(259200);
                          $("input[name=\"executiontimeoutbase\"],input[name=\"executiontimeoutrandbase\"]").val(259200);
                          evt.preventDefault();
                          return false;
                        })
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Search") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","search")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Specify the search words here."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Nickname") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","nickname")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("A nickname used in mail reports for identification."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Type") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","type")
                        .append(
                          $("<option/>")
                            .val("")
                            .text(e.t("All"))
                        )
                        .append(
                          e.obj_to_options(types)
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Choose the desired content type."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Method") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","method")
                        .append(
                          e.obj_to_options(methods)
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Choose the desired method."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Size min") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","sizemin")
                    )
                    .append(
                      $("<span/>")
                        .text("MB")
                        .addClass("suffix")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Enter the minimum file size."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Size max") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","sizemax")
                    )
                    .append(
                      $("<span/>")
                        .text("MB")
                        .addClass("suffix")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Enter the maximum file size."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Exec.timeout") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr({
                          name: "executiontimeoutbase",
                          size: 10
                        }).val(259200)
                    )
                    .append(
                      $("<span/>")
                        .text("s")
                        .addClass("suffix")
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>")
                        .text("+ " +  e.t("random") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr({
                          name: "executiontimeoutrandbase",
                          size: 10
                        }).val(259200)
                    )
                    .append(
                      $("<span/>")
                        .text("s")
                        .addClass("suffix")
                    )

                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("How long to wait between searches."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Extension") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","extension")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Enter the file extension."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Move-to-path") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","movetopath")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("A path to move the downloaded files to when they complete."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Mail") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","sendmail")
                        .append(
                          e.obj_to_options({
                            "0": e.t("Inactive"),
                            "1": e.t("Active")
                          })
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Whether to send mails about it or not."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("Status") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","status")
                        .append(
                          e.obj_to_options({
                            "0": e.t("Inactive"),
                            "1": e.t("Active")
                          })
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Whether the search is active or not."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<button/>")
                        .addClass("marginated")
                        .text(e.t("Save"))
                        .attr("id", "button_submit_insert_or_update_search")
                    )
                )
            );

          e.lock_form("#insert_or_update_search_form", true);

          $("#content")
            .append(
              $("<ul/>")
                .attr("id", "stats")
            );

          $("#insert_or_update_search_form").submit(function (evt) {
            let data = $(evt.currentTarget).serializeArray();
            let post = {
                action: "insert_or_update_search"
              };

            // lock down the form
            e.lock_form(evt.currentTarget, true);

            // flatten serialized array
            Object.keys(data).forEach(function (i) {
              post[data[i].name] = data[i].value;
            });

            e.requests.push($.postJSON(".", post, function (data) {
              if (!e.verify_response(data)) {
                e.lock_form("#insert_or_update_search_form", false);
                return false;
              }

              e.reload_page();
            }));

            evt.preventDefault();
            return false;
          });

          // get searches
          e.emule.get("searches", "", function (data) {
            let div_manage = null;
            let event_a_delete;
            let event_a_edit;

            // when clicking on delete links
            event_a_delete = function (evt) {
              // get row data
              let rowdata = $(evt.currentTarget).parents("tr:first").prop("data");

              if (window.confirm("Are you sure that you want to delete \"" + rowdata.search + "\" (#" + rowdata.id + ")?")) {
                e.requests.push($.getJSON(
                  "./?action=delete_search&id_searches=" + rowdata.id,
                  function (d) {
                    if (!e.verify_response(d)) {
                      return false;
                    }
                    e.reload_page();
                  }
                ));
              }

              evt.preventDefault();
              return false;
            };

            // when clicking on edit links
            event_a_edit = function (evt) {
              // get row data
              let rowdata = $(evt.currentTarget).parents("tr:first").prop("data");

              // load form with data
              $("#insert_or_update_search_form input[name='extension']").val(rowdata.extension);
              $("#insert_or_update_search_form input[name='executiontimeoutbase']").val(rowdata.executiontimeoutbase);
              $("#insert_or_update_search_form input[name='executiontimeoutrandbase']").val(rowdata.executiontimeoutrandbase);
              $("#insert_or_update_search_form input[name='id_searches']").val(rowdata.id);
              $("#insert_or_update_search_form input[name='search']").val(rowdata.search);
              $("#insert_or_update_search_form select[name='sendmail']").val(rowdata.sendmail);
              $("#insert_or_update_search_form input[name='sizemin']").val(rowdata.sizemin);
              $("#insert_or_update_search_form input[name='sizemax']").val(rowdata.sizemax);
              $("#insert_or_update_search_form select[name='status']").val(rowdata.status);
              $("#insert_or_update_search_form select[name='method']").val(rowdata.method);
              $("#insert_or_update_search_form input[name='movetopath']").val(rowdata.movetopath);
              $("#insert_or_update_search_form input[name='nickname']").val(rowdata.nickname);
              $("#insert_or_update_search_form select[name='type']").val(rowdata.type);

              evt.preventDefault();
              return false;
            };

            // walk searches
            Object.keys(data.data.searches).forEach(function (i){
              div_manage = $("<div/>")
                .append(
                  $("<a/>")
                    .attr("href", "#")
                    .addClass("button")
                    .text(e.t("Edit"))
                    .click(event_a_edit)
                )
                .append(" ")
                .append(
                  $("<a/>")
                    .attr("href", "#")
                    .addClass("button")
                    .text(e.t("Remove"))
                    .click(event_a_delete)
                );

              e.make.table_tr("#searches tbody", data.data.searches[i],
              [
                data.data.searches[i].search,
                data.data.searches[i].nickname,
                data.data.searches[i].type,
                data.data.searches[i].sizemin + " MB",
                data.data.searches[i].sizemax + " MB",
                data.data.searches[i].extension,
                data.data.searches[i].method,
                e.tools.format_seconds(data.data.searches[i].executiontimeout),
                {inner_html: e.tools.format_date(data.data.searches[i].executed), classes: "date"},
                {inner_html: !Number.isNaN(Date.parse(data.data.searches[i].executed)) ? e.tools.timestamp_to_date(Date.parse(data.data.searches[i].executed) + (data.data.searches[i].executiontimeout * 1000)) : "", classes: "date"},
                {inner_html: data.data.searches[i].executions, classes: "counter"},
                {inner_html: data.data.searches[i].queuedfiles, classes: "counter"},
                data.data.searches[i].sendmail === 1 ? e.t("On") : e.t("Off"),
                data.data.searches[i].status === 1 ? e.t("On") : e.t("Off"),
                {inner_html: div_manage.children(), classes: "manage"}
              ],
              function (data, tr) {

                if (data.status === 1) {
                  tr.addClass("active");
                } else {
                  tr.addClass("inactive");
                }

                return tr;
              });
            // }
            });

            // walk stats and make li:s
            Object.keys(data.data.stats).forEach(function (i){
              $("#stats")
                .append(
                  $("<li/>")
                    .append(
                      (data.data.stats[i].rootpath.length ? data.data.stats[i].rootpath : data.data.stats[i].host + "/" + data.data.stats[i].hostpath) +
                      " - " +
                      data.data.stats[i].fileamount +
                      " " +
                      e.t("files")
                    )
                );
            });

            // remove loading
            $("#searches tbody tr.loading").remove();

            // unlock form
            e.lock_form("#insert_or_update_search_form", false);
          });

          return true;

        case "latest_queued":

          e.make.textbox("", e.t("A summary of the latest queued files."));

          // add chart container
          $("#content")
            .append(
              $("<div/>")
                .attr("id", "chart")
            );

          // make a table
          e.make.table("latest_queued", [e.t("File"), {inner_html: e.t("Search"), classes: "unimportant"}, e.t("Queued")]);

          // request data
          e.emule.get("latest_queued", "", function (data) {

            $("#chart").highcharts({
              chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false
              },
              credits: {
                enabled : false
              },
              title: {
                text: e.t("Total queued")
              },
              tooltip: {
              pointFormat: "{series.name}: <b>{point.y} " + e.t("files") + "</b>",
                percentageDecimals: 1
              },
              plotOptions: {
                pie: {
                  animation: false,
                  allowPointSelect: true,
                  cursor: "pointer",
                  dataLabels: {
                  enabled: true,
                  color: "#000000",
                  connectorColor: "#000000",
                  formatter: function() {
                    return "<b>"+ this.point.name +"</b>: "+ this.point.y +" files";
                  }
                }
              }
              },
              series: [{
                type: "pie",
                name: "",
                data: data.data.files_queued_stats,
                size: 150
              }]
            });

            // walk the result
            Object.keys(data.data.files_queued).forEach(function (i){
              let tmp = "";

              // walk the searches
              Object.keys(data.data.searches).forEach(function (j) {
                // does this search match this file?
                if (data.data.files_queued[i].id_searches === data.data.searches[j].id && tmp === "") {
                  // then take the search text for this
                  tmp = data.data.searches[j].search;
                }
              });

              // make a new row in the table
              e.make.table_tr("#latest_queued tbody", data.data.files_queued[i], [data.data.files_queued[i].name, {inner_html: tmp, classes: "unimportant"}, data.data.files_queued[i].created]);
            });

            // remove loading
            $("#latest_queued tbody tr.loading").remove();
          });

          return true;

        case "log":

          e.make.textbox("", e.t("This is an output of the latest logmessages."));

          // make a table
          e.make.table("log", [e.t("Type"), e.t("Message"), e.t("Created")]);

          // fetch results
          e.emule.get("log", "", function (data) {

            let trmaker;

            // invalid status?
            if (data.status !== "ok") {
              if (data.status === "error") {
                window.alert(data.data.message);
              } else {
                window.alert(e.t("Bad response from server"));
              }
            }

            trmaker = function (data, tr) {
              // var i = 0;
              let inner_html = $("<div><table><tbody></tbody></table></div>");
              let parsed_json;

              // tr maker callback
              if (data.data.toLowerCase().indexOf("running search") !== -1) {
                tr.addClass("logcategory_search");

              } else if (data.data.toLowerCase().indexOf("scanning") !== -1) {
                tr.addClass("logcategory_scan");
              } else if (data.data.toLowerCase().indexOf("queued") !== -1) {
                tr.addClass("logcategory_queued");
              // is this json?
              } else if (data.data.substring(0,1) === "{" ) {
                // try to parse it as json
                parsed_json = $.parseJSON(data.data);

                if (typeof parsed_json === "object") {

                  // walk data
                  Object.keys(parsed_json).forEach(function (i){
                    if (parsed_json.hasOwnProperty(i)) {
                      // append the data
                      inner_html.find("tbody").append(
                        $("<tr/>")
                          .append(
                            $("<td/>").text(e.t(e.tools.upperCaseFirstLetter(i).replace("_", " ")))
                          )
                          .append(
                            $("<td/>").text(parsed_json[i])
                          )
                      );
                    }
                  });

                  tr
                  .find("td:eq(1)")
                  .empty()
                  .append(inner_html);
                }
              }

              return tr;
            };

            // walk logmessages
            Object.keys(data.data.logmessages).forEach(function (i){
              e.make.table_tr(
                $("#log>tbody"),
                data.data.logmessages[i],
                [e.t(e.logmessage_type_descriptions_short[data.data.logmessages[i].type]), data.data.logmessages[i].data, data.data.logmessages[i].created],
                trmaker
              );
            });

            // remove loading
            $("#log tbody tr.loading").remove();
          });

          return true;

        case "dumped":

          e.make.textbox("", e.t("A list of files that are not available."));

          // do a table
          e.make.table("dumped", [e.t("Check"), e.t("File"), e.t("Created")]);

          // fetch results
          e.emule.get("dumped", "", function (data) {

            // invalid status?
            if (data.status !== "ok") {
              if (data.status === "error") {
                window.alert(data.data.message);
              } else {
                window.alert(e.t("Bad response from server"));
              }
            }

            Object.keys(data.data.files_dumped).forEach(function (i){
              $("#dumped tbody")
                .append(
                  $("<tr/>")
                    .data("data", data.data.files_dumped[i])
                    .append(
                      $("<td/>").append(
                        $("<input/>")
                          .attr({
                            type: "checkbox",
                            name: "id_files[]"
                          })
                          .val(data.data.files_dumped[i].id)
                      )
                    )
                    .append(
                      $("<td/>").html(data.data.files_dumped[i].name)
                    )
                    .append(
                      $("<td/>").text(data.data.files_dumped[i].created)
                    )
                );
            });

            // remove loading
            $("#dumped tbody tr.loading").remove();
          });

          return true;
        case "parameters":

          e.make.textbox("", e.t("On this page you can manage settings."));

          // do the form
          $("#content")
            .append(
              $("<form/>")
                .attr("id", "insert_or_update_parameters_form")
                .append(
                  $("<fieldset/>")
                    .append(
                      $("<label/>").text(e.t("E-mail enabled") + ":")
                    )
                    .append(
                      $("<select/>")
                        .attr("name","email_enabled")
                        .append(
                          e.obj_to_options({
                            "0": e.t("Inactive"),
                            "1": e.t("Active")
                          })
                        )
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Used to activate the e-mail function."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("E-mail address") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","email_address")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("An e-mail adress to send reports to."))
                    )
                    .append(
                      $("<br/>")
                    )
                  .append(
                      $("<label/>").text(e.t("E-mail timeout") + ":")
                    )
                    .append(
                      $("<input/>")
                        .addClass("text")
                        .attr("name","email_timeout")
                    )
                    .append(
                      $("<span/>")
                        .text("s")
                        .addClass("suffix")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Timeout to wait between e-mails."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<label/>").text(e.t("E-mail last sent") + ":")
                    )
                    .append(
                      $("<span/>")
                        .addClass("value")
                        .attr("name","email_last_sent")
                        .text("0000-00-00 00:00:00")
                    )
                    .append(
                      $("<span/>")
                        .addClass("description")
                        .text(e.t("Last time an e-mail was sent."))
                    )
                    .append(
                      $("<br/>")
                    )
                    .append(
                      $("<button/>")
                        .addClass("marginated")
                        .text(e.t("Save"))
                        .attr("id", "button_submit_insert_or_update_parmeters")
                    )
                    .append(
                      $("<br/>")
                    )
                )
            );

          e.lock_form("#insert_or_update_parameters_form", true);

          $("#insert_or_update_parameters_form").submit(function (evt) {
            let data = $(evt.currentTarget).serializeArray();
            let post = {
                action: "insert_or_update_parameters"
              };

            // lock down the form
            e.lock_form(evt.currentTarget, true);

            // flatten serialized array
            Object.keys(data).forEach(function (i){
              post[data[i].name] = data[i].value;
            });

            e.requests.push($.postJSON(".", post, function (data) {
              if (!e.verify_response(data)) {
                e.lock_form("#insert_or_update_parameters_form", false);
                return false;
              }
              e.lock_form("#insert_or_update_parameters_form", false);
              e.reload_page();
            }));

            evt.preventDefault();
            return false;
          });

          // get current client pumps
          e.emule.get("parameters", {}, function (data) {
            // invalid status?
            if (data.status !== "ok") {
              if (data.status === "error") {
                window.alert(data.data.message);
              } else {
                window.alert(e.t("Bad response from server"));
              }
            }

            // walk parameters
            Object.keys(data.data.parameters).forEach(function (i){
              if (data.data.parameters.hasOwnProperty(i)) {
                if (i === "email_last_sent") {
                  $("#insert_or_update_parameters_form [name=\"" + i + "\"]").text(data.data.parameters[i]);
                } else {
                  $("#insert_or_update_parameters_form [name=\"" + i + "\"]").val(data.data.parameters[i]);
                }
              }
            });

            e.lock_form("#insert_or_update_parameters_form", false);
          });

          return true;
<?php } ?>
      }

      return false;
    };

    // menu handler
    $("#menu ul a").click(function (evt) {
      let a = $(evt.currentTarget).attr("href");

      $("#menu ul li.selected").removeClass("selected");
      $(evt.currentTarget).parents("li:first").addClass("selected");
      e.switch_page(
        a.substr(a.indexOf("=") + 1, a.length)
      );
      evt.preventDefault();
      return false;
    });
<?php if (is_logged_in()) { ?>
    // the find box
    $("#findbox #find").keyup(function () {
      if (e.timeouts.findbox !== undefined) {
        window.clearTimeout(e.timeouts.findbox);
      }

      // make a key press timeout
      e.timeouts.findbox = window.setTimeout(function () {
        let find = $.trim($("#findbox #find").val());

        // nothing to search for?
        if (!find.length) {
          // empty the find results table, if there is one
          $("#content table#findresult").empty();
          return true;
        }

        e.requests.push($.getJSON("?format=json&view=find&find=" + $("#findbox #find").val(), function (data) {
          let tbody = $("<tbody/>");

          if (!e.verify_response(data)) {
            return false;
          }

          // no find results table available?
          if (!$("#content table#findresult").length) {

            // then empty and put in a table
            $("#content")
              .empty()
              .append(
                $("<table/>")
                  .attr("id", "findresult")
                  .append(
                    $("<thead/>")
                      .append(
                        $("<tr/>")
                          .append(
                            $("<th/>")
                              .text(e.t("Filename"))
                          )
                          .append(
                            $("<th/>")
                              .text(e.t("Exists"))
                          )
                          .append(
                            $("<th/>")
                              .text(e.t("Created"))
                          )
                      )
                  )
              )
              ;
          // or a find results table is there
          } else {
            // remove the tbody from the table
            $("#content table#findresult tbody").remove();
          }

          // walk results
          Object.keys(data.data.findresult).forEach(function (i){
            if (data.data.findresult.hasOwnProperty(i)) {
              // append this result to the table body
              tbody
                .append(
                  $("<tr/>")
                    .append(
                      $("<td/>")
                        .append(
                          data.data.findresult[i].name
                        )
                    )
                    .append(
                      $("<td/>")
                        .append(
                          data.data.findresult[i].existing
                        )
                    )
                    .append(
                      $("<td/>")
                        .append(
                          data.data.findresult[i].created
                        )
                    )
                );
            }
          });

          $("#content table#findresult").append(tbody);
        }));
        return true;
      }, 250);
    });
<?php } ?>
    // switch page to something
    e.switch_page(view);
<?php if (is_logged_in()) { ?>
    e.tools.determine_incognito_mode();
<?php } ?>
  });
}());
