import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

window.Alpine = Alpine;
Alpine.plugin(focus);

const temSequencia = (texto) => {
    const linhas = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm'];
    const minusculo = texto.toLowerCase();
    for (let i = 0; i + 4 <= minusculo.length; i++) {
        const trecho = minusculo.slice(i, i + 4);
        const passos = [...trecho].slice(1).map((c, j) => c.charCodeAt(0) - trecho.charCodeAt(j));
        if (/^[a-z0-9]{4}$/.test(trecho) && (passos.every((p) => p === 1) || passos.every((p) => p === -1))) {
            return true;
        }
        if (linhas.some((l) => l.includes(trecho) || [...l].reverse().join('').includes(trecho))) {
            return true;
        }
    }

    return false;
};

Alpine.data('senha', () => ({
    valor: '',
    get requisitos() {
        const v = this.valor;

        return [
            { texto: '8 ou mais caracteres', ok: v.length >= 8 },
            { texto: 'Letras maiúsculas e minúsculas', ok: /\p{Ll}/u.test(v) && /\p{Lu}/u.test(v) },
            { texto: 'Pelo menos um número', ok: /\p{N}/u.test(v) },
            { texto: 'Pelo menos um símbolo (! @ # $)', ok: /[\p{Z}\p{S}\p{P}]/u.test(v) },
            { texto: 'Sem sequências (1234, abcd) ou repetições (aaa)', ok: v !== '' && !/(.)\1\1/iu.test(v) && !temSequencia(v) },
        ];
    },
}));

Alpine.start();

const formatarCentavos = (texto) => {
    const digitos = texto.replace(/\D/g, '').replace(/^0+/, '').slice(0, 11);
    if (digitos === '') {
        return '';
    }
    const centavos = digitos.padStart(3, '0');
    const reais = centavos.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${reais},${centavos.slice(-2)}`;
};

const paraCentavos = (valor) => {
    const [inteiro, decimal = ''] = valor.includes(',') ? valor.replace(/\./g, '').split(',') : valor.split('.');

    return inteiro + decimal.padEnd(2, '0').slice(0, 2);
};

document.querySelectorAll('input[data-dinheiro]').forEach((campo) => {
    if (campo.value !== '') {
        campo.value = formatarCentavos(paraCentavos(campo.value));
    }
    campo.addEventListener('input', () => {
        campo.value = formatarCentavos(campo.value);
    });
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form.hasAttribute('data-envio-unico')) {
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach((botao) => {
            botao.disabled = true;
        });
    }
});
