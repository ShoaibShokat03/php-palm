<?php

namespace Frontend\Palm;

/**
 * File Upload Helper for Views
 * 
 * Provides file upload helpers for forms
 */
class FileUploadHelper
{
    /**
     * Generate file input with upload preview
     */
    public static function input(string $name, array $options = []): string
    {
        $id = $options['id'] ?? 'file-' . $name;
        $accept = $options['accept'] ?? '*';
        $multiple = $options['multiple'] ?? false;
        $maxSize = $options['max_size'] ?? '10MB';
        $preview = $options['preview'] ?? false;
        $class = $options['class'] ?? 'form-control';
        $required = $options['required'] ?? false;

        $attrs = [
            'type' => 'file',
            'name' => $name,
            'id' => $id,
            'class' => $class,
            'accept' => $accept,
        ];

        if ($multiple) {
            $attrs['multiple'] = 'multiple';
        }

        if ($required) {
            $attrs['required'] = 'required';
        }

        if ($maxSize) {
            $attrs['data-max-size'] = $maxSize;
        }

        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$value) . '"';
        }

        $html = '<input' . $attrString . '>';

        if ($preview) {
            $html .= self::getPreviewScript($id, $accept);
        }

        if ($maxSize) {
            $html .= '<small class="form-text text-muted">Max size: ' . htmlspecialchars($maxSize) . '</small>';
        }

        return $html;
    }

    /**
     * Generate image upload with preview
     */
    public static function image(string $name, array $options = []): string
    {
        $options['accept'] = $options['accept'] ?? 'image/*';
        $options['preview'] = true;
        return self::input($name, $options);
    }

    /**
     * Get preview script for file uploads
     */
    protected static function getPreviewScript(string $id, string $accept): string
    {
        $previewId = $id . '-preview';
        
        return <<<HTML
<div id="{$previewId}" class="file-preview mt-2"></div>
<script>
(function() {
    const input = document.getElementById('{$id}');
    const preview = document.getElementById('{$previewId}');
    
    if (!input || !preview) return;
    
    input.addEventListener('change', function(e) {
        const files = e.target.files;
        preview.innerHTML = '';
        
        if (files.length === 0) return;
        
        Array.from(files).forEach(function(file) {
            const div = document.createElement('div');
            div.className = 'file-preview-item mb-2';
            
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.style.maxWidth = '200px';
                img.style.maxHeight = '200px';
                img.className = 'img-thumbnail';
                div.appendChild(img);
            }
            
            const nameDiv = document.createElement('div');
            nameDiv.textContent = file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
            div.appendChild(nameDiv);
            
            preview.appendChild(div);
        });
    });
})();
</script>
HTML;
    }

    /**
     * Validate uploaded file
     */
    public static function validate(array $file, array $rules = []): array
    {
        $errors = [];
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No file uploaded';
            return $errors;
        }

        $maxSize = $rules['max_size'] ?? 10485760; // 10MB default
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size';
        }

        $allowedTypes = $rules['allowed_types'] ?? null;
        if ($allowedTypes !== null) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes, true)) {
                $errors[] = 'File type not allowed';
            }
        }

        $allowedExtensions = $rules['allowed_extensions'] ?? null;
        if ($allowedExtensions !== null) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                $errors[] = 'File extension not allowed';
            }
        }

        return $errors;
    }
}

