<?php

namespace Tests\Feature;

use App\Events\ProjectUpdated;
use App\Models\Badge;
use App\Models\File;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Class FilesTest.
 *
 * @author annejan@badge.team
 */
class FilesTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function testUploadFile(): void
    {
        $stub = __DIR__.'/heart.png';
        $name = Str::random(8).'.png';
        $path = sys_get_temp_dir().'/'.$name;
        copy($stub, $path);
        $file = new UploadedFile($path, $name, 'image/png', null, true);
        $user = factory(User::class)->create();
        $this->be($user);
        $project = factory(Project::class)->create();

        $response = $this
            ->actingAs($user)
            ->post('/upload/'.$project->versions->last()->id, ['file' => $file]);
        $response->assertStatus(200);

        $this->assertCount(2, File::all()); // you get a free __init__.py
        /** @var File $file */
        $file = File::first();
        $this->assertEquals('__init__.py', $file->name);
        /** @var File $file */
        $file = File::where('name', '!=', '__init__.py')->first();
        $this->assertEquals($name, $file->name);
    }

//    public function testUploadIllegalFile(): void
//    {
//        $stub = __DIR__.'/empty.zip';
//        $name = Str::random(8).'.zip';
//        $path = sys_get_temp_dir().'/'.$name;
//        copy($stub, $path);
//        $file = new UploadedFile($path, $name, 'applications/zip', null, true);
//        $user = factory(User::class)->create();
//        $this->be($user);
//        $project = factory(Project::class)->create();
//
//        $response = $this
//            ->actingAs($user)
//            ->post('/upload/'.$project->versions->last()->id, ['file' => $file]);
//        $response->assertStatus(302);
//    }

    /**
     * Check the files edit page functions.
     */
    public function testFilesEdit(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $response = $this
            ->actingAs($user)
            ->get('/files/'.$file->id.'/edit');
        $response->assertStatus(200);
    }

    /**
     * Check the files edit page functions for other users.
     */
    public function testFilesEditOtherUser(): void
    {
        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $response = $this
            ->actingAs($otherUser)
            ->get('/files/'.$file->id.'/edit');
        $response->assertStatus(403);
    }

    /**
     * Check the files edit page functions for other users.
     */
    public function testFilesEditCollaboratingUser(): void
    {
        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $file->version->project->collaborators()->attach($otherUser);
        $response = $this
            ->actingAs($otherUser)
            ->get('/files/'.$file->id.'/edit');
        $response->assertStatus(200);
    }

    /**
     * Check the files edit page doesn't work for git projects.
     */
    public function testFilesEditGit(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create();
        $file->version->project->git = 'some.uri';
        $file->version->project->save();
        $response = $this
            ->actingAs($user)
            ->get('/files/'.$file->id.'/edit');
        $response->assertStatus(403);
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesUpdate(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create();
        $data = 'import time
time.localtime()';
        $response = $this
            ->actingAs($user)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertRedirect('/projects/'.$file->version->project->slug.'/edit')->assertSessionHas('successes');
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertEquals($data, $file->content);
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesUpdateNonPy(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'info.txt']);
        $data = 'info';
        $response = $this
            ->actingAs($user)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertRedirect('/projects/'.$file->version->project->slug.'/edit')->assertSessionHas('successes');
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertEquals($data, $file->content);
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesUpdateMarkdown(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'README.md']);
        $data = '# test

text';
        $response = $this
            ->actingAs($user)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertRedirect('/projects/'.$file->version->project->slug.'/edit')
            ->assertSessionHas('successes')
            ->assertSessionHasNoErrors();
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertEquals($data, $file->content);
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesUpdateJson(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.json']);
        $data = json_encode(['tests' => ['test1', 'test2']]);
        $response = $this
            ->actingAs($user)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertRedirect('/projects/'.$file->version->project->slug.'/edit')
            ->assertSessionHas('successes')
            ->assertSessionHasNoErrors();
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertEquals($data, $file->content);
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesUpdateVerilog(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.v']);
        $data = '`default_nettype none
module chip (
  output  O_LED_R
  );
  wire  w_led_r;
  assign w_led_r = 1\'b0;
  assign O_LED_R = w_led_r;
endmodule';
        $response = $this
            ->actingAs($user)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertRedirect('/projects/'.$file->version->project->slug.'/edit')
            ->assertSessionHas('successes')
            ->assertSessionHasNoErrors();
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertEquals($data, $file->content);
    }

    /**
     * Check the files can't be stored by other users.
     */
    public function testFilesUpdateOtherUser(): void
    {
        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $data = 'import time
time.localtime()';
        $response = $this
            ->actingAs($otherUser)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertStatus(403);
    }

    /**
     * Check the files can't be stored by other users.
     */
    public function testFilesUpdateCollaboratingUser(): void
    {
        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $file->version->project->collaborators()->attach($otherUser);
        $data = 'import time
time.localtime()';
        $response = $this
            ->actingAs($otherUser)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertStatus(302);
    }

    /**
     * Check the files can't be updated when project uses git.
     */
    public function testFilesUpdateGit(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create();
        $file->version->project->git = 'some.uri';
        $file->version->project->save();
        $data = 'import time
time.localtime()';
        $response = $this
            ->actingAs($user)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertStatus(403);
    }

    /**
     * Check the files can be deleted.
     */
    public function testFilesDestroy(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $response = $this
            ->actingAs($user)
            ->call('delete', '/files/'.$file->id);
        $response->assertRedirect('/projects/'.$file->version->project->slug.'/edit')->assertSessionHas('successes');
    }

    /**
     * Check the files can't be deleted from git managed project.
     */
    public function testFilesDestroyGit(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create();
        $file->version->project->git = 'some.uri';
        $file->version->project->save();
        $response = $this
            ->actingAs($user)
            ->call('delete', '/files/'.$file->id);
        $response->assertStatus(403);
    }

    /**
     * Check the files create page functions.
     */
    public function testFilesCreate(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $version = factory(Version::class)->create();
        $response = $this
            ->actingAs($user)
            ->get('/files/create?version='.$version->id);
        $response->assertStatus(200)
            ->assertViewHas('version', Version::find($version->id));
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesStore(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $version = factory(Version::class)->create();
        $response = $this
            ->actingAs($user)
            ->post('/files', ['name' => 'test.py', 'file_content' => '# test', 'version_id' => $version->id]);
        $response->assertRedirect('/projects/'.$version->project->slug.'/edit')
            ->assertSessionHas('successes');
        /** @var File $file */
        $file = File::all()->last();
        $this->assertEquals('test.py', $file->name);
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesStoreNameTooLarge(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $version = factory(Version::class)->create();
        $response = $this
            ->actingAs($user)
            ->post('/files', ['name' => $this->faker->text(1024), 'file_content' => '# test', 'version_id' => $version->id]);
        $response->assertRedirect('/files/create')
            ->assertSessionHasErrors();
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesUpdateLintWarning(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $data = 'import time';
        $response = $this
            ->actingAs($user)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertRedirect('/files/'.$file->id.'/edit')->assertSessionHas('warnings');
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesUpdateLintError(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $data = 'imprt time';
        $response = $this
            ->actingAs($user)
            ->call('put', '/files/'.$file->id, ['file_content' => $data]);
        $response->assertRedirect('/files/'.$file->id.'/edit')->assertSessionHasErrors();
    }

    /**
     * Check the files can be viewed (publicly).
     */
    public function testFilesView(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $response = $this
            ->call('get', '/files/'.$file->id);
        $response->assertStatus(200)->assertViewHas(['file']);
    }

    /**
     * Check the files can be downloaded (publicly).
     */
    public function testFilesDownload(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $file = factory(File::class)->create();
        $response = $this
            ->call('get', '/download/'.$file->id);
        $response->assertStatus(200)->assertHeader('Content-Type', 'application/x-python-code');
    }

    /**
     * Check the files create icon magic page functions.
     */
    public function testFilesCreateIcon(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $version = factory(Version::class)->create();
        $response = $this
            ->actingAs($user)
            ->get('/create-icon?version='.$version->id);
        $response->assertRedirect()
            ->assertSessionHas('successes');
        /** @var File $file */
        $file = File::get()->last();
        $this->assertEquals('icon.py', $file->name);
        $this->assertEquals('icon = ([0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000,'.
            ' 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000,'.
            ' 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000,'.
            ' 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000,'.
            ' 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000,'.
            ' 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000,'.
            ' 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000,'.
            ' 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000, 0x00000000,'.
            ' 0x00000000, 0x00000000], 1)', $file->content);
    }

    /**
     * Check the files can be stored.
     */
    public function testFilesCreateIconNameTooLarge(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $version = factory(Version::class)->create();
        $response = $this
            ->actingAs($user)
            ->post('/create-icon?version='.$version->id, ['name' => $this->faker->text(1024)]);
        $response->assertRedirect('/projects/'.$version->project->slug.'/edit')
            ->assertSessionHasErrors();
    }

    /**
     * Check the files can be linted.
     */
    public function testFilesLintSuccess(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.json']);
        $data = json_encode(['tests' => ['test1', 'test2']]);
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/lint-content/'.$file->id, ['file_content' => $data]);
        $response->assertStatus(200)->assertExactJson(['linting' => 'started']);
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertNotEquals($data, $file->content);
        Event::assertDispatched(ProjectUpdated::class, function ($e) {
            $this->assertEquals('success', $e->type);

            return true;
        });
    }

    /**
     * Check the files can be linted.
     */
    public function testFilesLintWarning(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.py']);
        $data = 'import neopixel';
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/lint-content/'.$file->id, ['file_content' => $data]);
        $response->assertStatus(200)->assertExactJson(['linting' => 'started']);
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertNotEquals($data, $file->content);
        Event::assertDispatched(ProjectUpdated::class, function ($e) {
            $this->assertEquals('warning', $e->type);

            return true;
        });
    }

    /**
     * Check the files can be linted.
     */
    public function testFilesLintDanger(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.py']);
        $data = 'improt system';
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/lint-content/'.$file->id, ['file_content' => $data]);
        $response->assertStatus(200)->assertExactJson(['linting' => 'started']);
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertNotEquals($data, $file->content);
        Event::assertDispatched(ProjectUpdated::class, function ($e) {
            $this->assertEquals('danger', $e->type);

            return true;
        });
    }

    /**
     * Check the files can't be linted.
     */
    public function testFilesLintInfo(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.txt']);
        $data = 'This is a file';
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/lint-content/'.$file->id, ['file_content' => $data]);
        $response->assertStatus(200)->assertExactJson(['linting' => 'started']);
        /** @var File $file */
        $file = File::find($file->id);
        $this->assertNotEquals($data, $file->content);
        Event::assertDispatched(ProjectUpdated::class, function ($e) use ($file) {
            $this->assertEquals('info', $e->type);
            $this->assertEquals('File '.$file->name.' currently not lintable.', $e->message);

            return true;
        });
    }

    /**
     * Check the files can't be processed.
     */
    public function testFilesProcessInfo(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.txt']);
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/process-file/'.$file->id);
        $response->assertStatus(200)->assertExactJson(['processing' => 'started']);
        /** @var File $file */
        $file = File::find($file->id);
        Event::assertDispatched(ProjectUpdated::class, function ($e) use ($file) {
            $this->assertEquals('info', $e->type);
            $this->assertEquals('File '.$file->name.' currently not processable.', $e->message);

            return true;
        });
    }

    /**
     * Check the files can't be processed.
     */
    public function testFilesProcessNoCommands(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.v', 'content' => '`default_nettype none
module chip (
  output  O_LED_R
  );
  wire  w_led_r;
  assign w_led_r = 1\'b0;
  assign O_LED_R = w_led_r;
endmodule']);
        $files = File::count();
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/process-file/'.$file->id);
        $response->assertStatus(200)->assertExactJson(['processing' => 'started']);
        /** @var File $file */
        $file = File::find($file->id);
        Event::assertDispatched(ProjectUpdated::class, function ($e) use ($file) {
            $this->assertEquals('danger', $e->type);
            $this->assertEquals('No badges with workable commands for project: '.$file->version->project->name, $e->message);

            return true;
        });
        $this->assertCount($files, File::all());
    }

    /**
     * Check the files can't be processed.
     */
    public function testFilesProcessNoConstraints(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.v', 'content' => '`default_nettype none
module chip (
  output  O_LED_R
  );
  wire  w_led_r;
  assign w_led_r = 1\'b0;
  assign O_LED_R = w_led_r;
endmodule']);
        $files = File::count();
        $badge = factory(Badge::class)->create([
            'commands' => 'echo VDL > OUT',
        ]);
        $file->version->project->badges()->attach($badge);
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/process-file/'.$file->id);
        $response->assertStatus(200)->assertExactJson(['processing' => 'started']);
        $i = 0;
        Event::assertDispatched(ProjectUpdated::class, function ($e) use ($badge, &$i) {
            if ($i == 0) {
                $this->assertEquals('warning', $e->type);
                $this->assertEquals('No constraints for badge: '.$badge->name, $e->message);
            }
            $i++;

            return true;
        });
        $this->assertCount($files, File::all());
    }

    /**
     * Check the files can be processed.
     */
    public function testFilesProcessSuccess(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.v', 'content' => '`default_nettype none
module chip (
  output  O_LED_R
  );
  wire  w_led_r;
  assign w_led_r = 1\'b0;
  assign O_LED_R = w_led_r;
endmodule']);
        /** @var Badge $badge */
        $badge = factory(Badge::class)->create([
            'constraints' => 'set_io O_LED_R	39',
            'commands'    => 'yosys -q -p "read_verilog -noautowire VDL ; check ; clean ; synth_ice40 -blif VDL.blif"
# arachne-pnr -d 5k -P sg48 -p PCF VDL.blif -o VDL.txt
arachne-pnr -p PCF VDL.blif -o VDL.txt
icepack VDL.txt OUT',
        ]);
        $file->version->project->badges()->attach($badge);
        $files = File::count();
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/process-file/'.$file->id);
        $response->assertStatus(200)->assertExactJson(['processing' => 'started']);
        /** @var File $generated */
        $generated = File::get()->last();
        Event::assertDispatched(ProjectUpdated::class, function ($e) use ($generated) {
            $this->assertEquals('success', $e->type);
            $this->assertEquals('File '.$generated->name.' generated.', $e->message);

            return true;
        });
        $this->assertCount($files + 1, File::all());
        $this->assertEquals($file->baseName.'_'.$badge->slug.'.bin', $generated->name);
        $this->assertGreaterThan(32200, strlen($generated->content));
        $this->assertLessThan(32300, strlen($generated->content));
    }

    /**
     * Check the files can't be processed.
     */
    public function testFilesProcessError(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        /** @var File $file */
        $file = factory(File::class)->create(['name' => 'test.v', 'content' => '`default_nettype none
module chip (
  output  O_LED_R
  );
  wire  w_led_r;
  assign w_led_r = 1\'b0;
  assign O_LED_R = w_led_r;
endmodule']);
        /** @var Badge $badge */
        $badge = factory(Badge::class)->create([
            'constraints' => 'set_io O_LED_R	39',
            'commands'    => 'echo lol && some typo',
        ]);
        $file->version->project->badges()->attach($badge);
        $files = File::count();
        Event::fake();
        $response = $this
            ->actingAs($user)
            ->json('post', '/process-file/'.$file->id);
        $response->assertStatus(200)->assertExactJson(['processing' => 'started']);
        $i = 0;
        Event::assertDispatched(ProjectUpdated::class, function ($e) use (&$i) {
            if ($i == 0) {
                $this->assertEquals('danger', $e->type);
            }
            if ($i == 1) {
                $this->assertEquals('warning', $e->type);
                $this->assertEquals("lol\n", $e->message);
            }
            $i++;

            return true;
        });
        $this->assertCount($files, File::all());
    }
}
