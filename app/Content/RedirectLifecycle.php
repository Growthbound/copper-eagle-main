<?php

namespace App\Content;

use App\Content\Exceptions\ContentValidationFailed;
use App\Models\RedirectAuditEvent;
use App\Models\RedirectRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RedirectLifecycle
{
    public function __construct(private RedirectValidator $validator) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): RedirectRule
    {
        return DB::transaction(function () use ($attributes, $actor): RedirectRule {
            $this->lockGraph();
            $this->validate($attributes);
            $rule = RedirectRule::create([
                ...$attributes,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->audit($rule, $actor, 'create', null, $this->snapshot($rule));

            return $rule;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(RedirectRule $rule, array $attributes, User $actor): RedirectRule
    {
        return DB::transaction(function () use ($rule, $attributes, $actor): RedirectRule {
            $this->lockGraph();
            $stored = RedirectRule::query()->lockForUpdate()->findOrFail($rule->id);
            $this->validate($attributes, $stored);
            $before = $this->snapshot($stored);
            $stored->update([...$attributes, 'updated_by' => $actor->id]);
            $this->audit($stored, $actor, 'update', $before, $this->snapshot($stored));

            return $stored->fresh();
        });
    }

    public function toggle(RedirectRule $rule, bool $enabled, User $actor): RedirectRule
    {
        return DB::transaction(function () use ($rule, $enabled, $actor): RedirectRule {
            $this->lockGraph();
            $stored = RedirectRule::query()->lockForUpdate()->findOrFail($rule->id);
            $this->validate([
                'source_path' => $stored->source_path,
                'target' => $stored->target,
                'status_code' => $stored->status_code,
                'enabled' => $enabled,
            ], $stored);
            $before = $this->snapshot($stored);
            $stored->update(['enabled' => $enabled, 'updated_by' => $actor->id]);
            $this->audit($stored, $actor, 'toggle', $before, $this->snapshot($stored));

            return $stored->fresh();
        });
    }

    public function delete(RedirectRule $rule, User $actor): void
    {
        DB::transaction(function () use ($rule, $actor): void {
            $this->lockGraph();
            $stored = RedirectRule::query()->lockForUpdate()->findOrFail($rule->id);
            $this->audit($stored, $actor, 'delete', $this->snapshot($stored), null);
            $stored->delete();
        });
    }

    private function lockGraph(): void
    {
        DB::table('content_operation_locks')
            ->where('name', 'redirect-graph')
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    private function validate(
        array $attributes,
        ?RedirectRule $ignore = null,
    ): void {
        $result = $this->validator->validate($attributes, $ignore);

        if ($result->hasErrors()) {
            throw new ContentValidationFailed($result);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(RedirectRule $rule): array
    {
        return [
            'id' => $rule->id,
            'source_path' => $rule->source_path,
            'target' => $rule->target,
            'status_code' => $rule->status_code,
            'enabled' => $rule->enabled,
            'notes' => $rule->notes,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(
        RedirectRule $rule,
        User $actor,
        string $action,
        ?array $before,
        ?array $after,
    ): void {
        $header = request()->header('X-Request-ID');
        $requestId = is_string($header)
            && preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $header) === 1
                ? $header
                : (string) Str::uuid();

        RedirectAuditEvent::create([
            'redirect_rule_id' => $rule->id,
            'user_id' => $actor->id,
            'action' => $action,
            'request_id' => $requestId,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
