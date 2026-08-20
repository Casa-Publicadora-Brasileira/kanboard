<?= $this->render('companyAgile:task/modal_header', array('modal_title' => t('Attach a document'), 'task' => $task)) ?>
<?= $this->app->component('file-upload', array(
    'csrf'              => $this->app->getToken()->getReusableCSRFToken(),
    'maxSize'           => $max_size,
    'url'               => $this->url->to('TaskFileController', 'save', array('task_id' => $task['id'])),
    'labelDropzone'     => t('Drag and drop your files here'),
    'labelOr'           => t('or'),
    'labelChooseFiles'  => t('choose files'),
    'labelOversize'     => $max_size > 0 ? t('The maximum allowed file size is %sB.', $this->text->bytes($max_size)) : null,
    'labelSuccess'      => t('All files have been uploaded successfully.'),
    'labelCloseSuccess' => t('Close this window'),
    'labelUploadError'  => t('Unable to upload this file.'),
)) ?>
<?= $this->modal->submitButtons(array('submitLabel' => t('Upload files'), 'disabled' => true)) ?>
