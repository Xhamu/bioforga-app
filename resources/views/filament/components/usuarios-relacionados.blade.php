@php
    $usuarios = $proveedorId ? \App\Models\User::where('proveedor_id', $proveedorId)->get() : collect();
@endphp

<div class="space-y-4 w-full">
    @if ($usuarios->isEmpty())
        <p class="text-sm text-gray-500">No hay usuarios asociados a este proveedor.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 w-full">
            <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-black">Nombre</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-black">Correo electrónico</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($usuarios as $usuario)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-black">
                                <a href="/usuarios/{{ $usuario->id }}/edit" class="text-black hover:underline">
                                    {{ $usuario->name }} {{ $usuario->apellidos }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-black">
                                {{ $usuario->email }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
