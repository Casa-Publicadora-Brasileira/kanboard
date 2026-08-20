<div class="ca-management-picker" data-ca-management-picker data-filter-name="<?= $this->text->e($name) ?>" data-placeholder="<?= $this->text->e($placeholder) ?>"<?= $remote_url !== '' ? ' data-remote-url="'.$this->text->e($remote_url).'"' : '' ?>>
    <label><?= $this->text->e($label) ?></label>
    <select class="ca-management-picker-native" tabindex="-1" aria-hidden="true">
        <?php foreach ($items as $item_id => $item_label): ?>
            <option value="<?= (int) $item_id ?>"<?= (int) $item_id === (int) $value ? ' selected' : '' ?>><?= $this->text->e($item_label) ?></option>
        <?php endforeach ?>
    </select>
    <div class="ca-management-picker-control">
        <input type="text" class="ca-management-picker-trigger<?= (int) $value === 0 ? ' is-placeholder' : '' ?>" role="combobox" aria-haspopup="listbox" aria-expanded="false" autocomplete="off" value="<?= $this->text->e((int) $value > 0 && isset($items[$value]) ? $items[$value] : '') ?>" placeholder="<?= $this->text->e($placeholder) ?>">
        <button type="button" class="ca-management-picker-clear" aria-label="<?= $this->text->e($clear_label) ?>"<?= (int) $value === 0 ? ' hidden' : '' ?>><i class="fa fa-times" aria-hidden="true"></i></button>
        <i class="fa fa-chevron-down ca-management-picker-chevron" aria-hidden="true"></i>
    </div>
    <div class="ca-management-picker-panel" hidden>
        <div class="ca-management-picker-options" role="listbox">
            <?php foreach ($items as $item_id => $item_label): ?><?php if ((int) $item_id === 0) continue ?>
                <button type="button" role="option" data-value="<?= (int) $item_id ?>" aria-selected="<?= (int) $item_id === (int) $value ? 'true' : 'false' ?>"><?= $this->text->e($item_label) ?></button>
            <?php endforeach ?>
        </div>
    </div>
</div>
