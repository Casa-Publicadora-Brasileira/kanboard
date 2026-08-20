<div class="ca-form-field">
    <label for="ca-advanced-issue-type"><?= t('CompanyAgile: Issue type') ?></label>
    <select id="ca-advanced-issue-type" name="issue_type_id" required data-ca-issue-type-select>
        <?php foreach ($issue_types as $type): ?><option value="<?= (int) $type['id'] ?>" data-code="<?= $this->text->e($type['code']) ?>"<?= isset($values['issue_type_id']) && (int) $values['issue_type_id'] === (int) $type['id'] ? ' selected' : '' ?>><?= t('CompanyAgile issue type: '.$type['code']) ?></option><?php endforeach ?>
    </select>
</div>
<div class="ca-form-field" data-ca-points-field>
    <label for="ca-advanced-story-points"><?= t('CompanyAgile: Story Points') ?></label>
    <select id="ca-advanced-story-points" name="story_points">
        <option value=""><?= t('CompanyAgile: Not estimated') ?></option>
        <?php foreach (range(1, 5) as $point): ?><option value="<?= $point ?>"<?= isset($values['story_points']) && (string) $values['story_points'] === (string) $point ? ' selected' : '' ?>><?= $point ?></option><?php endforeach ?>
    </select>
</div>
