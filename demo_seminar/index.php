<!DOCTYPE html>
<html>
<head>
    <title>💀 XXE Lab 💀</title>
    <style>
        body {
            background-color: #000;
            color: #00ff00;
            font-family: 'Courier New', Courier, monospace;
            padding: 40px;
        }
        textarea {
            width: 100%;
            height: 300px;
            background: #111;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 15px;
            font-size: 16px;
        }
        button {
            background: #0f0;
            border: 1px solid #0f0;
            color: #000;
            font-weight: bold;
            padding: 10px 25px;
            margin-top: 10px;
            cursor: pointer;
            font-size: 16px;
            transition: 0.3s;
        }
        button:hover {
            background: #3f3;
        }
        h1 {
            color: #0f0;
            font-size: 30px;
        }
        pre {
            background: #111;
            padding: 15px;
            color: #0f0;
            border: 1px solid #0f0;
            margin-top: 20px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <h1>💥 XXE Impossible Exploit Playground 💥</h1>
    <form method="POST">
        <textarea name="xml"><?php echo htmlentities($_POST['xml'] ?? '', ENT_QUOTES | ENT_HTML5); ?></textarea><br>
        <button type="submit">💣 Exploit!</button>
    </form>
    <?php
    error_reporting(0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['xml'])) {
        $doc = new \DOMDocument();
        $doc->loadXML($_POST['xml']); // Parse 1
        $xml = $doc->saveXML();
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml, LIBXML_DTDLOAD | LIBXML_NONET); // Parse 2 + flags

        foreach ($doc->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                echo "<pre>⚠️ DOCTYPE Detected. Possible attack attempt.</pre>";
                throw new RuntimeException('Dangerous XML detected');
            }
        }
    }
    ?>
</body>
</html>
