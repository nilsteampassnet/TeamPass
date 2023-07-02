/**
*   Generate a random string
**/
function CreateRandomString(size, type = '') {
    let chars = '';

    // CHoose what kind of string we want
    if (type === 'num') {
        chars = '0123456789';
    } else if (type === 'num_no_0') {
        chars = '123456789';
    } else if (type === 'alpha') {
        chars = 'ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz';
    } else if (type === 'secure') {
        chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz&#@;!+-$*%';
    } else {
        chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz';
    }

    // Generate it
    let randomstring = '';
    for (let i = 0; i < size; i++) {
        let rnum = Math.floor(Math.random() * chars.length);
        randomstring += chars.substring(rnum, rnum + 1);
    }

    //return
    return randomstring;
}
