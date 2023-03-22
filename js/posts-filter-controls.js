"use strict";
$(() => {
    Object.assign(dotclear.msg, dotclear.getData("filter_controls"));
    const filter_reset_url = dotclear.getData("filter_reset_url");
    const reset_url = dotclear.isEmptyObject(filter_reset_url)
        ? "?"
        : filter_reset_url;
    const $filtersform = $("#filters-form");
    $filtersform.before(
        `<p><a id="filter-control" class="form-control" href="${reset_url}&p=relatedEntries&relatedEntries_filters=relatedEntries&id=${id}" style="display:inline">${dotclear.msg.filter_posts_list}</a></p>`
    );
    if (dotclear.msg.show_filters) {
        $("#filter-control")
            .addClass("open")
            .text(dotclear.msg.cancel_the_filter);
    } else {
        $filtersform.hide();
    }
    if (dotclear.getData("filter_options").auto_filter) {
        $('#filters-form input[type="submit"]').parent().hide();
        $("#filters-form select").on("input", () => {
            $filtersform[0].submit();
        });
        $('#filters-form input[type!="submit"]').on("focusin", function () {
            $(this).data("val", $(this).val());
        });
        $('#filters-form input[type!="submit"]').on("focusout", function () {
            if ($(this).val() !== $(this).data("val")) {
                $filtersform[0].submit();
            }
        });
    }
    dotclear.enterKeyInForm(
        "#filters-form",
        '#filters-form input[type="submit"]',
        "#filter-control"
    );
    $("#filter-control").on("click", function () {
        if ($(this).hasClass("open")) {
            if (dotclear.msg.show_filters) {
                return true;
            }
            $filtersform.hide();
            $(this).removeClass("open").text(dotclear.msg.filter_posts_list);
        } else {
            $filtersform.show();
            $(this).addClass("open").text(dotclear.msg.cancel_the_filter);
        }
        return false;
    });
    $("#filter-options-save").on("click", () => {
        const param = {
            f: "setListsOptions",
            xd_check: dotclear.nonce,
            id: $("#filters-options-id").val(),
            sort: $("#sortby").val(),
            order: $("#order").val(),
            nb: $("#nb").val(),
        };
        $.post("services.php", param)
            .done((data) => {
                const rsp = $(data).children("rsp")[0];
                if (rsp) {
                    const res = $(rsp).find("result")[0];
                    if (res) {
                        window.alert(res.getAttribute("msg"));
                    } else if (rsp.getAttribute("status") !== "ok") {
                        window.console.log("Dotclear REST server error");
                    }
                }
            })
            .fail((jqXHR, textStatus, errorThrown) => {
                window.console.log(
                    `AJAX ${textStatus} (status: ${jqXHR.status} ${errorThrown})`
                );
                window.alert("Server error");
            });
    });
});
