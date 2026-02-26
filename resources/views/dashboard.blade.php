<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">

                @can('createDiets')
                <button type="button" class="btn btn-success waves-effect waves-light" data-toggle="modal"
                    data-target=".bs-example-modal-lg">Crear</button>

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
                        @foreach ($pacientes as $paciente)
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ $paciente->id }}</td>
                            <td class="px-3 py-2 text-xs">{{ $paciente->habitation }}</td>
                            <td class="px-3 py-2 text-xs">{{ $paciente->namePatient }}</td>
                            <td class="px-3 py-2 text-xs">
                                @forelse ($paciente->time_food_array as $item)
                                <small class="badge badge-light-success">
                                    {{ ucfirst(str_replace('_', ' ', $item)) }}
                                </small>
                                @empty
                                <span class="text-gray-400">N/A</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2 text-xs">
                                @forelse ($paciente->consistency_array as $item)
                                <small class="badge badge-light-info">
                                    {{ ucfirst(str_replace('_', ' ', $item)) }}
                                </small>
                                @empty
                                <span class="text-gray-400">N/A</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2 text-xs">
                                @forelse ($paciente->specifications_array as $item)
                                <small class="badge badge-light-primary">
                                    {{ ucfirst(str_replace('_', ' ', $item)) }}
                                </small>
                                @empty
                                <span class="text-gray-400">N/A</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $paciente->observations }}</td>
                            <td class="px-3 py-2 text-xs">
                                @if ($paciente->isolation)
                                Sí
                                @else
                                No
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $paciente->changes }}</td>
                            <td class="px-3 py-2 text-xs text-right">
                                <button type="button" class="btn btn-xs btn-primary">
                                    <i class=" mdi mdi-square-edit-outline "></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-danger">Eliminar</button>
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
                    <h4 class="modal-title" id="myLargeModalLabel">Large modal</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('pacientes.save') }}" method="POST">
                        @csrf

                        <label for="habitation">Habitación</label>
                        <input type="text" name="habitation" placeholder=""
                            class="w-full border rounded px-2 py-1 mb-1">

                        <label for="namePatient">Nombre del Paciente</label>
                        <input type="text" name="namePatient" placeholder=""
                            class="w-full border rounded px-2 py-1 mb-1">

                        <label for="timeFood">Tiempo de Comida</label>
                        <select class="selectpicker form-control mb-1" name="timeFood[]" multiple data-style="btn-pink">
                            <option value="desayuno">Desayuno</option>
                            <option value="almuerzo">Almuerzo</option>
                            <option value="cena">Cena</option>
                            <option value="fraccionada">Fraccionada</option>
                        </select>

                        <label for="consistency">Consistencia</label>
                        <select class="selectpicker form-control mb-1" name="consistency[]" multiple
                            data-style="btn-light">
                            <option value="liquida_completa">Líquida Completa</option>
                            <option value="liquida_clara">Líquida Clara</option>
                            <option value="muy_blanda">Muy Blanda</option>
                            <option value="blanda_mecanica">Blanda mecánica</option>
                            <option value="supraglotica">Supraglótica</option>
                            <option value="normal">Normal</option>
                        </select>

                        <label for="specifications">Especificaciones</label>
                        <select class="selectpicker form-control mb-1" name="specifications[]" multiple
                            data-style="btn-primary">
                            <option value="hipoglucida">Hipoglúcida</option>
                            <option value="hiperproteica">Hiperprotéica</option>
                            <option value="hiposodica">Hiposódica</option>
                            <option value="hipograsa">Hipograsa</option>
                            <option value="astringente">Astringente</option>
                            <option value="alta_en_fibra">Alta en fibra</option>
                            <option value="sin_lacteos">Sin lácteos</option>
                            <option value="sin_irritantes">Sin irritantes</option>
                            <option value="hipercalorica">Hipercalórica</option>
                        </select>
                        <label for="observations">Observaciones</label>
                        <input type="text" name="observations" placeholder=""
                            class="w-full border rounded px-2 py-1 mb-1">

                        <label for="isolation">Aislamiento</label>
                        <select name="isolation" id="isolation" class="form-control">
                            <option value="si">Si</option>
                            <option value="no">No</option>
                        </select>

                        <label for="changes">Cambios en la dieta</label>
                        <select name="changes" id="changes" class="form-control">
                            <option value="cancelada">CANCELADA</option>
                            <option value="con_cambios">CON CAMBIOS</option>
                            <option value="aislado">SE AISLÓ</option>
                            <option value="ya_no_aislado">YA NO ESTA AISLADO</option>
                        </select>

                        <button type="submit" class="btn btn-dark px-3 py-1 mt-3 rounded float-right">Guardar</button>
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