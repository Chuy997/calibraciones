<!DOCTYPE html>
<html>
<head>
    <title>Verificación de Límites PHP</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1a1d23; color: #fff; }
        .info { background: #2d3139; padding: 20px; border-radius: 8px; margin: 10px 0; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
    <h1>Verificación de Configuración PHP</h1>
    
    <div class="info">
        <h2>Límites de Subida Actuales</h2>
        <p><strong>upload_max_filesize:</strong> 
            <span class="<?php echo (ini_get('upload_max_filesize') === '25M') ? 'success' : 'error'; ?>">
                <?php echo ini_get('upload_max_filesize'); ?>
            </span>
            <?php echo (ini_get('upload_max_filesize') === '25M') ? '✓ Correcto' : '✗ Debe ser 25M'; ?>
        </p>
        
        <p><strong>post_max_size:</strong> 
            <span class="<?php echo (ini_get('post_max_size') === '30M') ? 'success' : 'error'; ?>">
                <?php echo ini_get('post_max_size'); ?>
            </span>
            <?php echo (ini_get('post_max_size') === '30M') ? '✓ Correcto' : '✗ Debe ser 30M'; ?>
        </p>
        
        <p><strong>memory_limit:</strong> 
            <span class="success">
                <?php echo ini_get('memory_limit'); ?>
            </span>
        </p>
        
        <p><strong>max_execution_time:</strong> 
            <span class="success">
                <?php echo ini_get('max_execution_time'); ?> segundos
            </span>
        </p>
    </div>
    
    <div class="info">
        <h2>Resultado</h2>
        <?php 
        $upload = ini_get('upload_max_filesize');
        $post = ini_get('post_max_size');
        
        if ($upload === '25M' && $post === '30M') {
            echo '<p class="success">✓ ¡Configuración correcta! Ahora puedes subir fotos de hasta 25MB.</p>';
        } else {
            echo '<p class="error">✗ La configuración no se aplicó correctamente.</p>';
            echo '<p class="warning">⚠️ Posibles causas:</p>';
            echo '<ul>';
            echo '<li>Apache no se reinició correctamente</li>';
            echo '<li>Hay otro archivo .ini con mayor prioridad</li>';
            echo '<li>Se necesita reiniciar PHP-FPM (si está instalado)</li>';
            echo '</ul>';
        }
        ?>
    </div>
    
    <div class="info">
        <p><a href="admin.php" style="color: #0d6efd;">← Volver a Instrumentos</a></p>
    </div>
</body>
</html>
