<?php declare(strict_types = 1);

namespace PHPStan\PhpDocParser\Ast\Type;

use function ltrim;

/**
 * A fully qualified class-like name like \Foo\Bar.
 * It means the same class in every namespace.
 */
class FullyQualifiedNameNode implements NameNode
{

	use NameNodeTrait;

	/**
	 * @param string $name with or without a leading backslash
	 */
	public function __construct(string $name)
	{
		$this->name = ltrim($name, '\\');
	}

	public function toCodeString(): string
	{
		return '\\' . $this->name;
	}

	public function isFullyQualified(): bool
	{
		return true;
	}

}
