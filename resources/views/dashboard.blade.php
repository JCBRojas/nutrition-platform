<x-app-layout>
    {{-- <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot> --}}

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">

                @can('createDiets')
                <button type="button" class="btn btn-success waves-effect waves-light" data-toggle="modal"
                    data-target=".bs-example-modal-lg">Crear</button>
                @php
                    $roles = app\Models\Role::all();
                    var_dump($roles);
                @endphp
                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-xs">ID</th>
                            <th class="px-3 py-2 text-xs">Habitación</th>
                            <th class="px-3 py-2 text-xs">Paciente</th>
                            <th class="px-3 py-2 text-xs">Tiempo</th>
                            <th class="px-3 py-2 text-xs">Consistencia</th>
                            <th class="px-3 py-2 text-xs">Especificaciones</th>
                            <th class="px-3 py-2 text-xs">Observaciones</th>
                            <th class="px-3 py-2 text-xs">Aislamiento</th>
                            <th class="px-3 py-2 text-xs">Cambios</th>
                            <th class="px-3 py-2 text-xs text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @foreach ($diets as $diet)
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ $diet->id }}</td>
                            <td class="px-3 py-2 text-xs">{{ $diet->habitation }}</td>
                            <td class="px-3 py-2 text-xs">{{ $diet->name_patient }}</td>
                            <td class="px-3 py-2 text-xs">
                                @forelse ($diet->currentVersion->timeFood ?? [] as $item)
                                <small class="badge badge-light-info">
                                    {{ ucfirst(str_replace('_', ' ', $item)) }}
                                </small>
                                @empty
                                <span class="text-gray-400">N/A</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2 text-xs">
                                @forelse ($diet->currentVersion->consistency ?? [] as $item)
                                <small class="badge badge-light-info">
                                    {{ ucfirst(str_replace('_', ' ', $item)) }}
                                </small>
                                @empty
                                <span class="text-gray-400">N/A</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2 text-xs">
                                @forelse ($diet->currentVersion->specifications ?? [] as $item)
                                <small class="badge badge-light-primary">
                                    {{ ucfirst(str_replace('_', ' ', $item)) }}
                                </small>
                                @empty
                                <span class="text-gray-400">N/A</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $diet->currentVersion->observations ?? '' }}</td>
                            <td class="px-3 py-2 text-xs">
                                {{ $diet->currentVersion->isolation ?? '' }}
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $diet->currentVersion->changes ?? '' }}</td>
                            <td class="px-3 py-2 text-xs text-right">
                                <button type="button" class="btn btn-xs btn-primary" data-toggle="modal"
                                    data-target=".edit-diet{{ $diet->id }}-modal-lg">
                                    <span class=" mdi mdi-square-edit-outline ">edit</span>
                                </button>
                                <div class="modal fade edit-diet{{ $diet->id }}-modal-lg  text-left" tabindex="-1"
                                    role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true"
                                    style="display: none;">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h4 class="modal-title" id="myLargeModalLabel">Editar Dieta</h4>
                                                <button type="button" class="close" data-dismiss="modal"
                                                    aria-hidden="true">×</button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="{{ route('dietas.update', ['diet' => $diet->id]) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <x-form-diet :diet="$diet" />
                                                </form>

                                            </div>
                                        </div><!-- /.modal-content -->
                                    </div><!-- /.modal-dialog -->
                                </div><!-- /.modal -->
                                {{-- <button type="button" class="btn btn-xs btn-danger">Eliminar</button> --}}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endcan
            </div>
        </div>
    </div>

    <!--  Modal content for the above example -->
    <div class="modal fade bs-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
        aria-hidden="true" style="display: none;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myLargeModalLabel">Registrar Dieta</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('dietas.create') }}" method="POST">
                        @csrf
                        <x-form-diet :diet="null" />
                    </form>

                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <script src="{{ asset('assets/libs/jquery-nice-select/jquery.nice-select.min.js') }}"></script>
    <script src="{{ asset('assets/libs/switchery/switchery.min.js') }}"></script>
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/libs/bootstrap-select/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('assets/libs/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js') }}"></script>
    <script src="{{ asset('assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js') }}"></script>

    <!-- Init js-->
    <script src="{{ asset('assets/js/pages/form-advanced.init.js') }}"></script>

    <!-- App js -->
    <script src="{{ asset('assets/js/app.min.js') }}"></script>
</x-app-layout>