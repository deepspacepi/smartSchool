Για την υλοποίηση του LoRa Gateway χρησιμοποιήθηκε το Waveshare SX1303 868M LoRaWAN Gateway HAT μαζί με ένα Raspberry Pi. Αναλυτικές οδηγίες για την εγκατάσταση και τις βασικές ρυθμίσεις του μπορείτε να βρείτε στην επίσημη σελίδα του κατασκευαστή.

* https://www.waveshare.com/sx1302-868m-lorawan-gateway-b.htm?srsltid=AfmBOorKM2vcUppeAnWef2ca1UD0ZmKNLw7ZOC77XzxBjGE0Ix2vsILD
* https://www.waveshare.com/wiki/SX1302_LoRaWAN_Gateway_HAT

Στην συνέχεια παρουσιάζουμε τις βασικές ρυθμίσεις που απαιτήθηκαν για την εγκατάσταση του LoRa module στο Raspberry Pi ώστε να λειτουργεί ως LoRaWAN Gateway, καθώς και την σύνδεση του με το The Things Network (TTN).

Η εγκατάσταση των απαραίτητων προγραμμάτων για το Waveshare SX1303 868M LoRaWAN Gateway HAT εξαρτάται και από την έκδοση του Raspberry Pi αλλά και από την έκδοση του λειτουργικού που έχουμε εγκαταστήσει. Στην περίπτωση μας οι εντολές που χρησιμοποιήθηκαν ήταν οι παρακάτω.

```
wget https://files.waveshare.com/wiki/SX130X/demo/PI5/sx130x_hal_rpi5.zip
sudo unzip sx130x_hal_rpi5.zip
cd sx1302_hal_rpi5-master/
make clean all
make all
cp tools/reset_lgw.sh util_chip_id/
cp tools/reset_lgw.sh packet_forwarder/
```

Στην συνέχεια θα πρέπει να εκτελέσουμε το αντίστοιχο πρόγραμμα για να δούμε το ID του LoRa module το οποίο θα πρέπει να χρησιμοποιήσουμε για την σύνδεση του με το TTN. Για το σκοπό αυτό εκτελούμε τις παρακάτω εντολές αρκεί να είμαστε στον σωστό φάκελο.

```
cd util_chip_id/
sudo ./chip_id
```

