<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\History;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Image;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Printer;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Profile;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Tag;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $roles = Role::factory()->count(3)->create();
        History::factory()->create();
        $user = User::factory()->create();
        $user->roles()->sync($roles->pluck('id'));
        $image = Image::factory()->create([
            "imagable_id" => $user->id,
            "imagable_type" => User::class,
        ]);
        Image::factory()->create([
            "imagable_id" => $user->id,
            "imagable_type" => UncachedUser::class,
            "path" => $image->path,
        ]);
        Tag::factory()->count(5)->create();
        $post = Post::factory()->create();
        $uncachedPost = (new UncachedPost)->first();
        $post->tags()->attach(1);
        $uncachedPost->tags()->attach(1);
        Comment::factory()->count(5)
            ->create([
                "commentable_id" => $post->id,
                "commentable_type" => Post::class,
            ])
            ->each(function ($comment) {
                (new Comment)->create([
                    "commentable_id" => $comment->commentable_id,
                    "commentable_type" => UncachedPost::class,
                    "description" => $comment->description,
                    "subject" => $comment->subject . ' - uncached post',
                ]);
            });
        $publishers = Publisher::factory()->count(10)->create();
        Author::factory()->count(10)->create()
            ->each(function ($author) use ($publishers) {
                $profile = Profile::factory()->make();
                $profile->author_id = $author->id;
                $profile->save();
                Book::factory()->count(random_int(5, 25))
                    ->create([
                        "author_id" => $author->id,
                        "publisher_id" => $publishers[rand(0, 9)]->id,
                    ])
                    ->each(function ($book) {
                        Printer::factory()->create([
                            "book_id" => $book->id,
                        ]);
                    });
                Profile::factory()->make([
                    'author_id' => $author->id,
                ]);
            });

        $bookIds = (new Book)->all()->pluck('id');
        Store::factory()->count(10)->create()
            ->each(function ($store) use ($bookIds) {
                $store->books()->sync(rand($bookIds->min(), $bookIds->max()));
            });
    }
}