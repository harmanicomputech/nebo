@props(['name', 'class' => 'size-5'])
{{ svg('lucide-'.$name, $class, ['aria-hidden' => 'true', 'stroke-width' => '1.75']) }}
