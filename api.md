Make reservations
Methbod: POST

Arguments: an array of reservation objects. Each reservations must specify:

Argument	Type	Required	Description
idType	int	yes	The reservation type
start	datetime	yes	The date of this slot
timeout	datetime	no	The date when it will be released if it is not confirmed
idResource	int	no	The resource id returned from /api/resources to force a specific resource
name	string	no	The client's name
email	string	no	The client's email
customTag	string	no	Extra information for the club; a single word is recommended
Example:

curl https://mt.golfmanager.app/api/makeReservation \
 -u user:key \
 -d tenant=demo \
 -d 'reservations=[{"idType":1,"start":"2018-11-09T10:00:00%2B02:00"}]'
Response:

Return reservations with their ID in case they need to be canceled. It also returns the sale information.

Example:

{
    "cart": {
        "idSale": 7729,
        "quantity": 2,
        "total": 116.8,
        "units": 2
    },
    "reservations": [
        {
            "email": null,
            "end": "2019-07-06T08:00:00+02:00",
            "id": 40857,
            "idParentReservation": null,
            "idSale": 7729,
            "idType": 1,
            "isExtra": null,
            "name": "n.,m",
            "reference": "33DS",
            "start": "2019-07-06T07:50:00+02:00",
            "timeout": "2019-07-05T18:13:39.655208969+02:00"
        },
        {
            "email": null,
            "end": "2019-07-06T10:20:00+02:00",
            "id": 40859,
            "idParentReservation": null,
            "idSale": 7729,
            "idType": 29,
            "isExtra": null,
            "name": null,
            "reference": "33DS",
            "start": "2019-07-06T07:50:00+02:00",
            "timeout": "2019-07-05T18:13:39.655208969+02:00"
        }
    ]
}
Confirm reservations
Confirm reservations that where created with a timeout.

Methbod: POST

Argument	Type	Required	Description
tenant	string	no	
ids	array	yes	The reservations IDs
paymentMethod	string	no	The method used to pay for the reservations
If paymentMethod is present, it will be used to pay for all the reservations. If it isn't, only reservations that don't require payment will be confirmed, otherwise the API will throw an error. In order to confirm reservations with price zero, the payment method must be omitted. A payment method with that name must already exist in the database or the API will throw an error.

Example:

curl https://mt.golfmanager.app/api/confirmReservation \
 -u user:key \
 -d tenant=demo \
 -d 'ids=[234]'
Response:

If it succeeded or the error.

Example:

{ "success": true }
Cancel reservations
Cancel reservations by id.

Methbod: POST

Example:

curl https://mt.golfmanager.app/api/cancelReservation \
 -u user:key \
 -d tenant=demo \
 -d 'ids=[234]'
Response:

If it succeeded or the error.

Example:

{ "success": true }
