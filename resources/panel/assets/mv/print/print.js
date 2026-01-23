function printFun() {
    window.print();
}

window.onload = function() {
    document.getElementById("print").addEventListener("click", printFun);
};
printFun();
