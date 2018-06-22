<?php

namespace Odanijel\JsonDiff\Tests;


use Odanijel\JsonDiff\Exception;
use Odanijel\JsonDiff\JsonPointer;

class JsonPointerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @throws Exception
     */
    public function testProcess()
    {
        $json = new \stdClass();
        JsonPointer::add($json, ['l1','l2','l3'], 'hello!');
        $this->assertSame('{"l1":{"l2":{"l3":"hello!"}}}', json_encode($json));

        JsonPointer::add($json, ['l1', 'l2', 'l3'], 'hello again!', JsonPointer::SKIP_IF_ISSET);
        $this->assertSame('{"l1":{"l2":{"l3":"hello!"}}}', json_encode($json));

        JsonPointer::add($json, ['l1', 'l2', 'l3'], 'hello again!');
        $this->assertSame('{"l1":{"l2":{"l3":"hello again!"}}}', json_encode($json));

        JsonPointer::add($json, ['l1', 'l2', 'l3'], 'hello!');
        $this->assertSame('{"l1":{"l2":{"l3":"hello!"}}}', json_encode($json));

        $this->assertSame('{"l3":"hello!"}', json_encode(JsonPointer::get($json, JsonPointer::splitPath('/l1/l2'))));

        try {
            $this->assertSame('null', json_encode(JsonPointer::get($json, JsonPointer::splitPath('/l1/l2/non-existent'))));
        } catch (Exception $exception) {
            $this->assertSame('Key not found: non-existent', $exception->getMessage());
        }

        JsonPointer::remove($json, ['l1','l2']);
        $this->assertSame('{"l1":{}}', json_encode($json));

        JsonPointer::add($json, JsonPointer::splitPath('/l1/l2/0/0'), 0);
        JsonPointer::add($json, JsonPointer::splitPath('#/l1/l2/1/1'), 1);

        $this->assertSame('{"l1":{"l2":[[0],{"1":1}]}}', json_encode($json));

        $this->assertSame(1, JsonPointer::get($json, JsonPointer::splitPath('/l1/l2/1/1')));
        $this->assertSame(1, JsonPointer::getByPointer($json, '/l1/l2/1/1'));
    }

    /**
     * @throws Exception
     */
    public function testNumericKey()
    {
        $json = json_decode('{"l1":{"200":1}}');
        $this->assertSame(1, JsonPointer::get($json, JsonPointer::splitPath('/l1/200')));
    }


    public function testEscapeSegment()
    {
        $segment = '/project/{username}/{project}';
        $this->assertSame('~1project~1%7Busername%7D~1%7Bproject%7D', JsonPointer::escapeSegment($segment, true));
    }

    public function testBuildPath()
    {
        $pathItems = ['key1', '/project/{username}/{project}', 'key2'];

        $this->assertSame('/key1/~1project~1{username}~1{project}/key2',
            JsonPointer::buildPath($pathItems));
        $this->assertSame('#/key1/~1project~1%7Busername%7D~1%7Bproject%7D/key2',
            JsonPointer::buildPath($pathItems, true));
    }
}
