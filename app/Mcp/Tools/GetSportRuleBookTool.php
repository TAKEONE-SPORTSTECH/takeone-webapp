<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Sports\Package\SportPackages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Get a sport\'s rule book')]
#[Description('How a combat sport is run: its official styles (e.g. karate: wkf, shotokan; bjj: ibjjf) with their age groups, belts, medal rule, officials, corners and weight tables per competition kind (bjj: gi, nogi). Pass `event` (uuid) instead to read the rule book that event runs under, including a club\'s or federation\'s own custom edition. Optionally classify an athlete (gender Male/Female, age, weight kg, kind) — the same placement the entry desk makes.')]
class GetSportRuleBookTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'sport' => $schema->string()->description('Sport key: karate, taekwondo or bjj. Not needed with `event`.'),
            'style' => $schema->string()->description('One style only, e.g. wkf or shotokan. Omit for all of the sport\'s styles.'),
            'event' => $schema->string()->description('An event uuid: the rule book that event runs under.'),
            'with_tables' => $schema->boolean()->description('Include the weight tables (default false).'),
            'gender' => $schema->string()->enum(['Male', 'Female'])->description('Classify: the athlete\'s gender.'),
            'age' => $schema->integer()->description('Classify: age in years.'),
            'weight' => $schema->number()->description('Classify: weight in kg.'),
            'kind' => $schema->string()->description('Classify: competition kind, e.g. gi or nogi.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }

        $packs = app(SportPackages::class);
        $v = $request->validate([
            'sport' => 'nullable|string|in:'.implode(',', $packs->sports()),
            'style' => 'nullable|string|max:60|regex:/^[a-z0-9_\-]+$/',
            'event' => 'nullable|uuid',
            'with_tables' => 'nullable|boolean',
            'gender' => 'nullable|in:Male,Female',
            'age' => 'nullable|integer|min:3|max:100|required_with:gender,weight',
            'weight' => 'nullable|numeric|min:10|max:250|required_with:gender,age',
            'kind' => 'nullable|string|max:20|regex:/^[a-z]+$/',
        ]);
        $tables = (bool) ($v['with_tables'] ?? false);

        if (! empty($v['event'])) {
            $event = ClubEvent::where('uuid', $v['event'])->first();
            // One answer for missing and not-yours — an event's existence is not disclosed.
            if (! $event || ! app(EventAccess::class)->visible($event, $user) || ! ($book = $packs->forEvent($event))) {
                return Response::error('Event not found.');
            }
            $books = [$book];
        } elseif (! empty($v['sport'])) {
            $books = ! empty($v['style'])
                ? array_filter([$packs->style($v['sport'], $v['style'])])
                : array_values($packs->styles($v['sport']));
            if ($books === []) {
                return Response::error('No such style for this sport.');
            }
        } else {
            return Response::error('Pass a sport or an event.');
        }

        $out = [];
        foreach ($books as $book) {
            $row = $book->toArray($tables);
            $row['editions'] = $book->official ? array_keys($packs->editions($book->sport, $book->style)) : [];
            if (isset($v['gender'], $v['age'], $v['weight'])) {
                $c = $book->classify($v['gender'], (int) $v['age'], (float) $v['weight'], $v['kind'] ?? null);
                $row['classified'] = $c ? $c + ['division' => $book->divisionName($c['age_group'], $v['gender'], $c['category'])] : null;
            }
            $out[] = $row;
        }

        return Response::json(['rule_books' => $out]);
    }
}
