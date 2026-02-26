const content = document.getElementById("input"); // contenedor donde se crean los input
const btnNoche = document.getElementById("btnNoche"); // buttom turno noche
const btnCorrido = document.getElementById("btnCorrido"); // buttom turno Corrido
const btnExtra = document.getElementById("btnExtra"); // buttom turno extra
const arraDay = []; // Almacena los días seleccionados

let icon;
let btnClass;
let turnoSeleccionado = null;
/** Cambia el valor de los días seleccionados 
 * por el tipo de turno
 * */
function check_turno(turno) {
    if (turno == "c") {
        icon = "<span class='mdi mdi-alpha-c-circle pl-1 py-0'></span>";
        btnClass = "btn btn-primary";
        turnoSeleccionado = "c";
    } else if (turno == "n") {
        icon = "<span class='mdi mdi-alpha-n-circle '></span>";
        btnClass = "btn btn-dark";
        turnoSeleccionado = "n";
    } else {
        // new
        icon = "<span class='mdi mdi-alpha-a-circle '></span>";
        btnClass = "btn btn-purple";
        turnoSeleccionado = "a";
    }
    pintarBtnTurnoActivo(turno);
    ActivarBtn();
}

function pintarBtnTurnoActivo(btnSelected) {
    // Color para el turno seleccionado
    if (btnSelected == "c") {
        btnCorrido.className = "btn btn-sm btn-primary";
        btnNoche.className = "btn btn-sm btn-outline-dark";
        btnExtra.className = "btn btn-sm btn-outline-purple"; // new
    } else if (btnSelected == "n") {
        //new
        btnNoche.className = "btn btn-sm btn-dark";
        btnCorrido.className = "btn btn-sm btn-outline-primary";
        btnExtra.className = "btn btn-sm btn-outline-purple"; // new
    } else {
        //new
        btnExtra.className = "btn btn-sm btn-purple"; // new
        btnCorrido.className = "btn btn-sm btn-outline-primary"; // new
        btnNoche.className = "btn btn-sm btn-outline-dark"; // new
    }
}

function ActivarBtn() { //  activa btn de los días al seleccionar el turno
    for (let index = 1; index <= 31; index++) {
        document.getElementById("btn" + index).disabled = false;
    }
}

function charge($this) {
    if (turnoSeleccionado != null) {
        /* Creacion del input oculto con los atributos correspondientes */
        const inputCreate = document.createElement("input");

        inputCreate.value = $this;
        inputCreate.type = "hidden";
        inputCreate.id = $this; // **
        /* End creacion del input   */

        var button = document.getElementById("btn" + $this);

        button.className = btnClass;
        button.innerHTML = "<small class=''>" + $this + "</small>" + icon;


        if (arraDay.includes($this) == true) {
            // si cambia de turno se actualiza el atributo name del input
            if(document.getElementById($this).name == turnoSeleccionado+"[]" ){
                button.className = "btn btn-secondary";
                button.innerHTML =  $this;
                arraDay.splice(arraDay.indexOf($this), 1) // Elimina el valor del array
                document.getElementById($this).remove();    // Elimina el Input
            
            }else{
            document.getElementById($this).name = turnoSeleccionado + "[]"; // Actualiza el NAME del INPUT por el nuevo turno
            }
            
        } else {
            inputCreate.name = turnoSeleccionado + "[]";
            arraDay.push($this); // agrega los valores al array
            content.appendChild(inputCreate); // crea los input
        }

    }
    // console.log(arraDay);
    if(arraDay.length > 0)
        document.getElementById("submit").disabled = false;
}
