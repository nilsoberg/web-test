
class SunburstAppTransfer {
    constructor(sunburstApp, estUrl, gntUrl, jobId, jobKey) {
        this.app = sunburstApp;
        this.estUrl = estUrl;
        this.gntUrl = gntUrl;
        this.jobId = jobId;
        this.jobKey = jobKey;
    }

    makeOnSunburstCompleteFn() {
        const that = this;
        var onSunburstComplete = function () {
            var estClickFn = function() {
                var estPath = that.estUrl;
                var info = that.app.getCurrentNode();
                var encTaxName = encodeURIComponent(info.name);
                var args = [
                    "tax-id=" + that.jobId,
                    "tax-key=" + that.jobKey,
                    "tree-id=" + info.id,
                    "id-type=" + info.idType,
                    "tax-name=" + encTaxName,
                    "mode=tax",
                ];
                var urlArgs = args.join("&");
                var url = estPath + "?" + urlArgs;
                window.location = url;
            };
            var gndClickFn = function() {
                var gntPath = that.gntUrl;
                var info = that.app.getCurrentNode();
                var encTaxName = encodeURIComponent(info.name);
                var args = [
                    "tax-id=" + that.jobId,
                    "tax-key=" + that.jobKey,
                    "tree-id=" + info.id,
                    "id-type=" + info.idType,
                    "tax-name=" + encTaxName,
                ];
                var urlArgs = args.join("&");
                var url = gntPath + "?" + urlArgs;
                window.location = url;
            };
            that.app.addTransferAction("sunburst-transfer-to-est", "Transfer to EFI-EST", "Transfer to EFI-EST", () => estClickFn());
            that.app.addTransferAction("sunburst-transfer-to-gnt", "Transfer to EFI-GND Viewer", "Transfer to EFI-GND Viewer", () => gndClickFn());
        }
    
        return onSunburstComplete;
    }
}

window.SunburstAppTransfer = SunburstAppTransfer;

