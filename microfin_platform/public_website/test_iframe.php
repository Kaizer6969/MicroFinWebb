<!-- DELETE CANDIDATE: No in-repo references found; appears to be legacy, test, backup, or export-only. -->
<!DOCTYPE html>
<html>
<body>
    <iframe id="previewContainer" style="width: 100%; height: 600px;"></iframe>
    <script>
        var docHtml = `
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"><\/script>
<script>
window.addEventListener('message', function(e) {
    if (e.data && e.data.html !== undefined) {
        document.getElementById('preview-body-content').innerHTML = e.data.html;
    }
});
<\/script>
</head>
<body>
<div id="preview-body-content"></div>
</body>
</html>
        `;

        var iframe = document.getElementById('previewContainer');
        var html = '<h1 class="text-3xl text-blue-500">Hello Tailwind!</h1>';
        window.lastGeneratedHtml = html;
        var iframeInited = false;

        iframe.onload = function() {
            if (iframeInited) {
                iframe.contentWindow.postMessage({ html: window.lastGeneratedHtml }, '*');
                console.log("onload sent message");
            }
        };

        iframe.srcdoc = docHtml;
        iframeInited = true;

        setTimeout(() => {
            iframe.contentWindow.postMessage({ html: '<h1 class="text-3xl text-red-500">Delayed Message!</h1>' }, '*');
            console.log("delayed sent message");
        }, 1000);
    </script>
</body>
</html>
