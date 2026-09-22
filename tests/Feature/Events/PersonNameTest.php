<?php

namespace Tests\Feature\Events;

use App\Support\PersonName;
use Tests\TestCase;

/**
 * Two whole names, however many the competitor wrote down.
 *
 * The real strings here are taken from the Victory BJJ Championship entry
 * list, which is where the hall board ran out of room.
 */
class PersonNameTest extends TestCase
{
    public function test_a_long_name_keeps_only_its_first_and_family_parts(): void
    {
        $this->assertSame('Mohammed Almaqabi', PersonName::short('Mohammed Abdulla Mansoor Almaqabi'));
        $this->assertSame('Mohammed zainuddin', PersonName::short('Mohammed Abdulnabi ali zainuddin'));
        $this->assertSame('Samuel Ambrosio', PersonName::short('Samuel Queiroz Ambrosio'));
        $this->assertSame('Salman alshirawy', PersonName::short('Salman khaled alshirawy'));
    }

    public function test_a_short_name_is_returned_untouched(): void
    {
        $this->assertSame('Taqi Mayoof', PersonName::short('Taqi Mayoof'));
        $this->assertSame('عبدالله', PersonName::short('عبدالله'));
        $this->assertSame('Khalifa Hamad.', PersonName::short('Khalifa Hamad.'));
    }

    public function test_a_particle_travels_with_the_family_name_it_belongs_to(): void
    {
        // Dropping the article renames the family — "Mughniy." is not the same
        // name as "Al Mughniy.".
        $this->assertSame('Ali Al Mughniy.', PersonName::short('Ali Mohammed Al Mughniy.'));
        $this->assertSame('Pieter van der Berg', PersonName::short('Pieter Jan van der Berg'));
        $this->assertSame('Joao de Souza', PersonName::short('Joao Carlos de Souza'));
        $this->assertSame('محمد آل خليفة', PersonName::short('محمد عيسى آل خليفة'));
    }

    public function test_punctuation_between_the_parts_does_not_drop_a_particle(): void
    {
        // Written exactly this way in the entry list. The lone hyphen must not
        // stop the walk, or "al" is lost and the family is renamed.
        $this->assertSame('Hussein al - Sahlawi', PersonName::short('Hussein al - Sahlawi'));
    }

    public function test_nothing_it_cannot_split_is_ever_mangled(): void
    {
        $this->assertSame('', PersonName::short(null));
        $this->assertSame('', PersonName::short('   '));
        // Collapsed, never cut: double spaces and a pasted non-breaking space.
        $this->assertSame('Ahmed Masood', PersonName::short("Ahmed\u{00A0} Masood"));
        // A name that is nothing but particles absorbs them all rather than
        // looping or eating its first part — the string survives whole.
        $this->assertSame('Al Al Al', PersonName::short('Al Al Al'));
    }
}
