import React from 'react'

import mastercard from 'payment-icons/min/flat/mastercard.svg'
import visa from 'payment-icons/min/flat/visa.svg'
import giropay from '../../../assets/svg/giropay.svg'
import edenredLogo from '../../../assets/svg/Edenred_Logo.svg'
import cashLogo from '../../../assets/svg/dollar-bill-svgrepo-com.svg'

export default ({ code, height }) => {
  switch (code.toLowerCase()) {

    case 'card':
      return (
        <span>
          <img src={ visa } height={ height } className="mr-2" />
          <img src={ mastercard } height={ height } />
        </span>
      )

    case 'giropay':
      return (
        <img src={ giropay } height={ height } />
      )

    case 'edenred':
    case 'edenred+card':
      return (
        <img src={ edenredLogo } height={ height } />
      )

    case 'cash_on_delivery':
      return (
        <img src={ cashLogo } height={ height } />
      )
  }
}
