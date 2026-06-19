Για την υλοποίηση του LoRa Gateway χρησιμοποιήθηκε το Waveshare SX1303 868M LoRaWAN Gateway HAT μαζί με ένα Raspberry Pi. Αναλυτικές οδηγίες για την εγκατάσταση και τις βασικές ρυθμίσεις του μπορείτε να βρείτε στην επίσημη σελίδα του κατασκευαστή.

* https://www.waveshare.com/sx1302-868m-lorawan-gateway-b.htm?srsltid=AfmBOorKM2vcUppeAnWef2ca1UD0ZmKNLw7ZOC77XzxBjGE0Ix2vsILD
* https://www.waveshare.com/wiki/SX1302_LoRaWAN_Gateway_HAT

Στην συνέχεια παρουσιάζουμε τις βασικές ρυθμίσεις που απαιτήθηκαν για την εγκατάσταση του LoRa module στο Raspberry Pi ώστε να λειτουργεί ως LoRaWAN Gateway, καθώς και την σύνδεση του με το The Things Network (TTN).

Η εγκατάσταση των απαραίτητων προγραμμάτων για το Waveshare SX1303 868M LoRaWAN Gateway HAT εξαρτάται και από την έκδοση του Raspberry Pi αλλά και από την έκδοση του λειτουργικού που έχουμε εγκαταστήσει. Στην περίπτωση μας οι εντολές που χρησιμοποιήθηκαν ήταν οι παρακάτω.

```
sudo apt update
sudo apt install git
cd ~/Documents/
git clone https://github.com/siuwahzhong/sx1302_hal.git
cd sx1302_hal
git checkout ws-dev
make clean all
make all
cp tools/reset_lgw.sh util_chip_id/
cp tools/reset_lgw.sh packet_forwarder/
```

Στην συνέχεια θα πρέπει να εκτελέσουμε το αντίστοιχο πρόγραμμα για να δούμε το ID του LoRa module το οποίο θα πρέπει να χρησιμοποιήσουμε για την σύνδεση του με το TTN. Για το σκοπό αυτό εκτελούμε τις παρακάτω εντολές.

```
cd ~/Documents/sx1302_hal/util_chip_id/
./chip_id
```

Η εντολή θα μας επιστρέψει την τιμή EUI του concetrator module η οποία θα πρέπει να χρησιμοποιηθεί αργότερα για την σύνδεση του με το TTN. Ας υποθέσουμε ότι ένα παράδειγμα της πληροφορίας αυτής είναι η παρακάτω.
Σημείωση: Για ευνόητους λόγους ασφάλειας, η τιμή που παραθέτουμε δεν είναι η πραγματική τιμή.

```
INFO: concentrator EUI: 0x00ffddbb88664422
```

Στην συνέχεια χρειάζεται να δημιουργηθεί το configuration file του packet forwarder, δηλαδή του προγράμματος που κάνει προώθηση των πακέτων που λαμβάνονει το gateway προς το TTN.
Με τις παρακάτω εντολές γίνει αντιγραφή των βασικών ρυθμίσεων προς ένα νέο αρχείο το οποίο και θα χρησιμοποιειθεί αργότερα.


```
cd ~/Documents/sx1302_hal/packet_forwarder/
cp global_conf.json.sx1257.EU868 test_conf.json
```

Για την τροποποίηση των ρυθμίσεων απατείται ένας απλός κειμενογράφος όπως είναι ο nano σε περιβάλλον γραμμής εντολών.

```
nano test_conf.json
```

Και θα πρέπει να αλλάξουμε τις ρυθμίσεις για το gateway_ID, αλλά και για τις τιμές server_address, serv_port_up, serv_port_down. Να σημειώσουμε ότι το gateway_ID είναι το EUI του concentrator module που βρήκαμε προηγουμένως, ενώ οι υπόλοιπες ρυθμίσεις αντιστοιχούν στις τιμές που θα μας δοθούν από το TTN.


```
"gateway_conf": {
    "gateway_ID": "00ffddbb88664422",
    /* change with default server address/ports */
    "server_address": "eu1.cloud.thethings.network",
    "serv_port_up": 1700,
    "serv_port_down": 1700,
},
```


    
