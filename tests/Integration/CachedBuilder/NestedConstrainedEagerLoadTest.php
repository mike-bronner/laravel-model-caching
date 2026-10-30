<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Support\Facades\DB;

test('a nested eager load constraint keys each value and serves repeats from the cache', function (): void {
    $author = authorWithCommentedBook();
    $query = fn (string $description) => Author::where("id", $author->id)
        ->with(["books.comments" => whereDescription($description)])
        ->get()
        ->first();

    $first = commentsOfFirstBook($query("AAA"));
    $second = commentsOfFirstBook($query("BBB"));
    [$third, $thirdLog] = queriesRunBy(fn () => $query("AAA"));
    [$fourth, $fourthLog] = queriesRunBy(fn () => $query("BBB"));

    expect($first)->toBe(["AAA"])
        ->and($second)
        ->toBe(["BBB"])
        ->and(commentsOfFirstBook($third))
        ->toBe(["AAA"])
        ->and($thirdLog)
        ->toBe([])
        ->and(commentsOfFirstBook($fourth))
        ->toBe(["BBB"])
        ->and($fourthLog)
        ->toBe([]);
});

test('a nested constraint that depends on a captured flag keys each flag value', function (array $flags): void {
    $author = authorWithCommentedBook();
    // phpcs:ignore CleanCode.Functions.DisallowBooleanArgumentFlag.Found
    $query = fn (bool $onlyAaa) => Author::where("id", $author->id)
        ->with(["books.comments" => function ($query) use ($onlyAaa): void {
            if ($onlyAaa) {
                $query->where("description", "AAA");
            }
        }])
        ->get()
        ->first();

    $results = collect($flags)
        // phpcs:ignore CleanCode.Functions.DisallowBooleanArgumentFlag.Found
        ->mapWithKeys(fn (bool $flag) => [(int) $flag => commentsOfFirstBook($query($flag))]);

    expect($results->get(1))->toBe(["AAA"])
        ->and($results->get(0))
        ->toBe(["AAA", "BBB"]);
})->with([
    "flag true first" => [[true, false]],
    "flag false first" => [[false, true]],
]);

test('identical nested constraints from separate closures share one cache entry', function (): void {
    $author = authorWithCommentedBook();
    $query = fn () => Author::where("id", $author->id)
        ->with(["books.comments" => whereDescription("BBB")])
        ->get()
        ->first();

    $query();
    [$repeat, $log] = queriesRunBy($query);

    expect(commentsOfFirstBook($repeat))->toBe(["BBB"])
        ->and($log)
        ->toBe([]);
});

test('unconstrained and constrained dotted eager loads never share a cache entry', function (array $order): void {
    $author = authorWithCommentedBook();
    $reads = collect([
        "constrained" => fn () => Author::where("id", $author->id)
            ->with(["books.comments" => whereDescription("BBB")])
            ->get()
            ->first(),
        "unconstrained" => fn () => Author::where("id", $author->id)
            ->with("books.comments")
            ->get()
            ->first(),
    ]);

    $results = collect($order)
        ->mapWithKeys(fn (string $name) => [$name => commentsOfFirstBook($reads->get($name)())]);

    expect($results->get("constrained"))->toBe(["BBB"])
        ->and($results->get("unconstrained"))
        ->toBe(["AAA", "BBB"]);
})->with([
    "constrained first" => [["constrained", "unconstrained"]],
    "unconstrained first" => [["unconstrained", "constrained"]],
]);

test('an unconstrained dotted eager load stays cached', function (): void {
    $author = authorWithCommentedBook();
    $query = fn () => Author::where("id", $author->id)
        ->with("books.comments")
        ->get()
        ->first();

    $query();
    [$repeat, $log] = queriesRunBy($query);

    expect(commentsOfFirstBook($repeat))->toBe(["AAA", "BBB"])
        ->and($log)
        ->toBe([]);
});

test('a constraint on the last segment of a three segment path keys each value', function (): void {
    $author = authorWithPublishedBooks();
    $query = fn (string $title) => Author::where("id", $author->id)
        ->with(["books.publisher.books" => fn ($query) => $query->where("title", $title)])
        ->get()
        ->first();

    expect(publishedTitlesOfFirstBook($query("One")))->toBe(["One"])
        ->and(publishedTitlesOfFirstBook($query("Two")))
        ->toBe(["Two"]);
});

test('constraints on the middle and last segments of one path are each keyed', function (): void {
    $author = authorWithPublishedBooks();
    $query = fn (string $publisher, string $title) => Author::where("id", $author->id)
        ->with([
            "books.publisher" => fn ($query) => $query->where("name", $publisher),
            "books.publisher.books" => fn ($query) => $query->where("title", $title),
        ])
        ->get()
        ->first();

    expect(publishedTitlesOfFirstBook($query("Penguin", "One")))->toBe(["One"])
        ->and(publishedTitlesOfFirstBook($query("Penguin", "Two")))
        ->toBe(["Two"])
        ->and(publishedTitlesOfFirstBook($query("Other", "Two")))
        ->toBeNull();
});

test('a parent and its dotted child constrained in one call are each keyed', function (): void {
    $author = Author::factory()
        ->create();
    bookWithComments($author, ["AAA", "BBB"], ["description" => "AAA"]);
    bookWithComments($author, ["AAA", "BBB"], ["description" => "BBB"]);
    $query = fn (string $book, string $comment) => Author::where("id", $author->id)
        ->with([
            "books" => whereDescription($book),
            "books.comments" => whereDescription($comment),
        ])
        ->get()
        ->first();

    expect(commentsByBook($query("AAA", "BBB")))->toBe(["AAA" => ["BBB"]])
        ->and(commentsByBook($query("BBB", "AAA")))
        ->toBe(["BBB" => ["AAA"]])
        ->and(commentsByBook($query("BBB", "BBB")))
        ->toBe(["BBB" => ["BBB"]])
        ->and(commentsByBook($query("AAA", "AAA")))
        ->toBe(["AAA" => ["AAA"]]);
});

test('nested constraints that differ only in order are keyed', function (): void {
    $author = authorWithCommentedBook();
    $query = fn (string $direction) => Author::where("id", $author->id)
        ->with(["books.comments" => fn ($query) => $query->orderBy("description", $direction)])
        ->get()
        ->first();

    expect(commentsOfFirstBook($query("asc")))->toBe(["AAA", "BBB"])
        ->and(commentsOfFirstBook($query("desc")))
        ->toBe(["BBB", "AAA"]);
});

test('nested constraints that differ only in limit are keyed', function (): void {
    $author = authorWithCommentedBook();
    $query = fn (int $limit) => Author::where("id", $author->id)
        ->with(["books.comments" => fn ($query) => $query->orderBy("description")->limit($limit)])
        ->get()
        ->first();

    expect(commentsOfFirstBook($query(1)))->toBe(["AAA"])
        ->and(commentsOfFirstBook($query(2)))
        ->toBe(["AAA", "BBB"]);
});

test('nested constraints that differ only in selected columns are keyed', function (): void {
    $author = authorWithCommentedBook();
    $query = fn (string $column) => Author::where("id", $author->id)
        ->with(["books.comments" => fn ($query) => $query
            ->select("id", "commentable_id", "commentable_type", $column)])
        ->get()
        ->first();

    expect(commentColumnsOfFirstBook($query("description")))
        ->toBe(["commentable_id", "commentable_type", "description", "id"])
        ->and(commentColumnsOfFirstBook($query("subject")))
        ->toBe(["commentable_id", "commentable_type", "id", "subject"]);
});

test('nested column selections in the colon form are keyed', function (): void {
    $author = authorWithCommentedBook();
    $query = fn (string $column) => Author::where("id", $author->id)
        ->with("books.comments:id,commentable_id,commentable_type,{$column}")
        ->get()
        ->first();

    expect(commentColumnsOfFirstBook($query("description")))
        ->toBe(["commentable_id", "commentable_type", "description", "id"])
        ->and(commentColumnsOfFirstBook($query("subject")))
        ->toBe(["commentable_id", "commentable_type", "id", "subject"]);
});

test('nested constraints that differ only in a nested with are keyed', function (array $order): void {
    $author = authorWithCommentedBook();
    $reads = collect([
        "nested" => fn () => Author::where("id", $author->id)
            ->with(["books.comments" => fn ($query) => $query->with("commentable")])
            ->get()
            ->first(),
        "plain" => fn () => Author::where("id", $author->id)
            ->with(["books.comments" => fn ($query) => $query])
            ->get()
            ->first(),
    ]);

    $loaded = collect($order)
        ->mapWithKeys(fn (string $name) => [
            $name => $reads->get($name)()->books->first()->comments->first()->relationLoaded("commentable"),
        ]);

    expect($loaded->get("nested"))->toBeTrue()
        ->and($loaded->get("plain"))
        ->toBeFalse();
})->with([
    "nested with first" => [["nested", "plain"]],
    "plain first" => [["plain", "nested"]],
]);

test('a dotted path via a resolveRelationUsing relation is keyed', function (string $books, string $comments): void {
    Author::resolveRelationUsing(
            "nestedResolvedBooks",
            fn (Author $author) => $author->hasMany(Book::class),
        );
    Book::resolveRelationUsing(
            "nestedResolvedComments",
            fn (Book $book) => $book->morphMany(Comment::class, "commentable"),
        );
    $author = authorWithCommentedBook();
    $query = fn (string $description) => Author::where("id", $author->id)
        ->with(["{$books}.{$comments}" => whereDescription($description)])
        ->get()
        ->first();
    $descriptions = fn (Author $result) => $result->$books
        ->first()
        ->$comments
        ->pluck("description")
        ->all();

    $first = $descriptions($query("AAA"));
    $second = $descriptions($query("BBB"));
    [$repeat, $log] = queriesRunBy(fn () => $query("AAA"));

    expect($first)->toBe(["AAA"])
        ->and($second)
        ->toBe(["BBB"])
        ->and($descriptions($repeat))
        ->toBe(["AAA"])
        ->and($log)
        ->toBe([]);
})->with([
    "resolved last segment" => ["books", "nestedResolvedComments"],
    "resolved first segment" => ["nestedResolvedBooks", "comments"],
]);

test('a nested constraint through morph to runs uncached even if both models have the relation', function (): void {
    Comment::resolveRelationUsing(
            "comments",
            fn (Comment $comment) => $comment->morphMany(Comment::class, "commentable"),
        );
    $book = bookWithComments(Author::factory()->create(), ["AAA", "BBB"]);
    $commentId = DB::table("comments")
        ->where("commentable_id", $book->id)
        ->min("id");
    $query = fn (string $description) => Comment::where("id", $commentId)
        ->with(["commentable.comments" => whereDescription($description)])
        ->get()
        ->first();

    expect(readsWithLiveRepeat($query, commentsBesideComment(...), "AAA", "BBB"))
        ->toBe([["AAA"], ["BBB"], ["BBB"], true]);
});

test('a nested column selection through a morph to relation runs uncached', function (): void {
    $book = bookWithComments(Author::factory()->create(), ["AAA", "BBB"]);
    $commentId = DB::table("comments")
        ->where("commentable_id", $book->id)
        ->min("id");
    $query = fn (string $column) => Comment::where("id", $commentId)
        ->with("commentable.comments:id,commentable_id,commentable_type,{$column}")
        ->get()
        ->first();
    $columns = fn (Comment $comment) => collect($comment->commentable->comments->first()->getAttributes())
        ->keys()
        ->sort()
        ->values()
        ->all();

    expect(readsWithLiveRepeat($query, $columns, "description", "subject"))->toBe([
        ["commentable_id", "commentable_type", "description", "id"],
        ["commentable_id", "commentable_type", "id", "subject"],
        ["commentable_id", "commentable_type", "id", "subject"],
        true,
    ]);
});

test('a nested constraint through a morph to relation that declares no parameter runs uncached', function (): void {
    $book = bookWithComments(Author::factory()->create(), ["AAA", "BBB"]);
    $commentId = DB::table("comments")
        ->where("commentable_id", $book->id)
        ->min("id");
    $query = fn (string $description) => Comment::where("id", $commentId)
        ->with(["commentable.comments" => function () use ($description): void {
            // phpcs:ignore CleanCode.Methods.DeclaredParameters.DynamicArguments
            func_get_arg(0)->where("description", $description);
        }])
        ->get()
        ->first();

    expect(readsWithLiveRepeat($query, commentsBesideComment(...), "AAA", "BBB"))
        ->toBe([["AAA"], ["BBB"], ["BBB"], true]);
});

test('an unconstrained morph to path, which Laravel combines from no-op closures, stays cached', function (): void {
    $book = bookWithComments(Author::factory()->create(), ["AAA", "BBB"]);
    $commentId = DB::table("comments")
        ->where("commentable_id", $book->id)
        ->min("id");
    $query = fn () => Comment::where("id", $commentId)
        ->with("commentable.comments")
        ->get()
        ->first();

    $query();
    [$repeat, $log] = queriesRunBy($query);

    expect(commentsBesideComment($repeat))->toBe(["AAA", "BBB"])
        ->and($log)
        ->toBe([]);
});

test('a nested constraint with a value the key cannot encode runs uncached', function (): void {
    $author = Author::factory()
        ->create();
    $book = bookWithComments($author, ["AAA", "BBB"]);
    $live = DB::table("comments")
        ->where("commentable_id", $book->id)
        ->where("commentable_type", Book::class)
        ->where("description", "!=", streamHolding("AAA"))
        ->pluck("description")
        ->all();

    $cached = Author::where("id", $author->id)
        ->with(["books.comments" => fn ($query) => $query->where("description", "!=", streamHolding("AAA"))])
        ->get()
        ->first();

    expect($live)->not
        ->toBeEmpty()
        ->and(commentsOfFirstBook($cached))
        ->toBe($live)
        ->and(modelCacheKeys())
        ->toBe([]);
});

test('a non-relation dotted path raises Laravel\'s exception', function (string $path, ?Closure $constraint): void {
    $author = authorWithCommentedBook();
    $raised = fn (string $model) => rescue(
            fn () => (new $model)->where("id", $author->id)
                ->with($constraint ? [$path => $constraint] : $path)
                ->get(),
            fn (Throwable $exception) => $exception,
            false,
        );

    $cached = $raised(Author::class);
    $live = $raised(UncachedAuthor::class);

    expect($cached)->toBeInstanceOf(RelationNotFoundException::class)
        ->and($cached::class)
        ->toBe($live::class)
        ->and($cached->relation)
        ->toBe($live->relation);
})->with([
    "constrained last segment" => ["books.missing", whereDescription("AAA")],
    "constrained first segment" => ["missing.comments", whereDescription("AAA")],
    "unconstrained last segment" => ["books.missing", null],
]);

test('nested constraints are keyed for first and paginate', function (Closure $read): void {
    $author = authorWithCommentedBook();
    $query = fn (string $description) => $read(Author::where("id", $author->id)
        ->with(["books.comments" => whereDescription($description)]));

    expect(commentsOfFirstBook($query("AAA")))->toBe(["AAA"])
        ->and(commentsOfFirstBook($query("BBB")))
        ->toBe(["BBB"]);
})->with([
    "first" => [fn ($query) => $query->first()],
    "paginate" => [fn ($query) => $query->paginate()->first()],
]);
