<?php
// sales/pos_redirect.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Redirecting...</title>
</head>
<body>
    <a id="posLink" href="possys.php?NewInvoice=0" target="_blank"></a>
    <script type="text/javascript">
        // Simulate a click on the link immediately after the page loads
        document.getElementById('posLink').click();
        // Go back to the previous page (the Sales menu) in the original tab
        window.history.back();
    </script>
</body>
</html>