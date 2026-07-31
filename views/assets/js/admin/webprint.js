/**
 * WebPrint client library for micwallace/WebPrint
 * Direct HTTP POST API Client with Origin & Cookie ACL support
 * @author Michael Wallace (WallaceIT)
 * https://github.com/micwallace/WebPrint
 */
var WebPrint = function (init, opt) {
    var options = {
        relayHost: "127.0.0.1",
        relayPort: "8085",
        listPrinterCallback: null,
        listPortsCallback: null,
        readyCallback: null,
        errorCallback: null
    };

    if (typeof $ !== 'undefined' && $.extend) {
        $.extend(options, opt);
    } else if (opt) {
        for (var k in opt) { options[k] = opt[k]; }
    }

    var cookie = "";
    try { cookie = localStorage.getItem("webprint_auth") || ""; } catch(e){}

    function getOrigin() {
        return window.location.origin || (window.location.protocol + "//" + window.location.host);
    }

    function sendRequest(data, successCb, errorCb) {
        data.origin = getOrigin();
        data.cookie = cookie || "";

        var host = options.relayHost || "127.0.0.1";
        var port = options.relayPort || "8085";
        var url = "http://" + host + ":" + port + "/";

        if (typeof $ !== 'undefined' && $.ajax) {
            $.ajax({
                url: url,
                type: "POST",
                data: JSON.stringify(data),
                contentType: "text/plain; charset=utf-8",
                dataType: "json",
                success: function (res) {
                    if (res && res.cookie) {
                        cookie = res.cookie;
                        try { localStorage.setItem("webprint_auth", res.cookie); } catch(e){}
                    }
                    if (res && res.error) {
                        if (typeof errorCb === 'function') errorCb(res.error);
                        else if (typeof options.errorCallback === 'function') options.errorCallback(res.error);
                        return;
                    }
                    if (typeof successCb === 'function') successCb(res);
                },
                error: function (xhr, status, err) {
                    var msg = "Impossibile connettersi a WebPrint su " + url;
                    if (typeof errorCb === 'function') errorCb(msg);
                    else if (typeof options.errorCallback === 'function') options.errorCallback(msg);
                }
            });
        }
    }

    this.initSession = function (onReady) {
        sendRequest({ a: "init" }, function (res) {
            if (res && res.ready) {
                if (typeof onReady === 'function') onReady();
                if (typeof options.readyCallback === 'function') options.readyCallback();
            }
        });
    };

    this.requestPrinters = function (cb) {
        var fetchList = function() {
            sendRequest({ a: "listprinters" }, function (res) {
                if (res && res.printers) {
                    if (typeof cb === 'function') cb(res.printers);
                    if (typeof options.listPrinterCallback === 'function') options.listPrinterCallback(res.printers);
                }
            });
        };

        sendRequest({ a: "init" }, function (res) {
            fetchList();
        }, function(err) {
            fetchList();
        });
    };

    this.printRaw = function (data, printer) {
        var rawData = (typeof btoa === 'function' && !data.startsWith('JVBER')) ? btoa(data) : data;
        var payload = { a: "printraw", printer: printer, data: rawData };
        sendRequest(payload, function(res) {
            console.log("WebPrint printRaw result:", res);
        });
    };

    if (init) {
        this.initSession();
    }

    return this;
};

if (typeof window !== 'undefined') {
    window.WebPrint = WebPrint;
}
