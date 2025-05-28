<?php

namespace Dyrynda\Database\Support;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

trait CascadeSoftRestores
{
    /**
     * Boot the trait.
     *
     * Listen for the restoring event of a soft deleting model, and run
     * the cascade restore functionality for the given model.
     *
     * @return void
     */
    protected static function bootCascadeSoftRestores()
    {
        static::restoring(function ($model) {
            $model->validateCascadingSoftRestores();
            $model->runCascadingSoftRestore();
        });
    }

    /**
     * Validate that the model is using the SoftDeletes trait.
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function validateCascadingSoftRestores()
    {
        if (! $this->implementsSoftDelete()) {
            throw new InvalidArgumentException(sprintf(
                '%s does not implement Illuminate\Database\Eloquent\SoftDeletes',
                get_called_class()
            ));
        }
    }

    /**
     * Run the cascading soft restore for this model.
     *
     * @return void
     */
    protected function runCascadingSoftRestore()
    {
        foreach ($this->getActiveCascadingSoftRestores() as $relationship) {
            $this->cascadeSoftRestore($relationship);
        }
    }

    /**
     * Cascade restore the given relationship on the given mode.
     *
     * @param  string  $relationship
     * @return void
     */
    protected function cascadeSoftRestore($relationship)
    {
        $restore = $this->getCascadingSoftRestoreAction($relationship);

        $restore($this->{$relationship}());
    }

    /**
     * Get the cascading soft restore action for the given relationship.
     *
     * @param  string  $relationship
     * @return callable
     */
    protected function getCascadingSoftRestoreAction($relationship)
    {
        $relation = $this->{$relationship}();

        if ($relation instanceof HasOneOrMany || $relation instanceof MorphOneOrMany || $relation instanceof BelongsToMany) {
            return function ($relation) {
                $relation->onlyTrashed()->each(function ($model) {
                    // Only restore if the model was soft deleted after this model
                    if ($model->deleted_at && $model->deleted_at >= $this->deleted_at) {
                        $model->restore();
                    }
                });
            };
        }

        if ($relation instanceof BelongsToMany) {
            return function ($relation) {
                $relation->onlyTrashed()->each(function ($model) {
                    // Only restore if the model was soft deleted after this model
                    if ($model->deleted_at && $model->deleted_at >= $this->deleted_at) {
                        $model->restore();
                    }
                });
            };
        }

        throw new InvalidArgumentException(sprintf(
            '%s does not support restoring %s relationships.',
            __CLASS__,
            get_class($relation)
        ));
    }

    /**
     * Get the relationships that are currently configured for cascading soft restores.
     *
     * @return array
     */
    protected function getActiveCascadingSoftRestores()
    {
        return $this->cascadeRestores ?? [];
    }

    /**
     * Determine if the current model implements soft deletes.
     *
     * @return bool
     */
    protected function implementsSoftDelete()
    {
        return method_exists($this, 'runSoftDelete');
    }
}
